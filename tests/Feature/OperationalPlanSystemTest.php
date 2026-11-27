<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Department;
use App\Models\MainObjective;
use App\Models\PlanningYear;
use App\Models\Program;
use App\Models\SubObjective;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalPlanSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_can_login_and_access_all_management_pages(): void
    {
        $response = $this->post('/login', [
            'username' => 'admin',
            'password' => 'Admin@1234',
        ]);
        $response->assertRedirect(route('dashboard'));

        $routes = [
            '/', '/activities', '/activities/create', '/followups',
            '/evaluation', '/budgets', '/risks', '/reports',
            '/notifications', '/messages', '/departments', '/objectives',
            '/users', '/years', '/audit', '/trash', '/backups', '/profile',
        ];

        foreach ($routes as $url) {
            $res = $this->get($url);
            $res->assertStatus(200, "Route {$url} failed with status {$res->status()}");
        }
    }

    public function test_department_first_login_forces_password_change(): void
    {
        $res = $this->post('/login', [
            'username' => 'legal',
            'password' => 'Dept@1234',
        ]);
        $res->assertRedirect(route('password.first'));

        // Cannot bypass to dashboard before changing password
        $this->get('/')->assertRedirect(route('password.first'));

        // Change password
        $change = $this->post('/password/first-change', [
            'current_password' => 'Dept@1234',
            'new_password' => 'NewLegal@2027',
            'new_password_confirmation' => 'NewLegal@2027',
        ]);
        $change->assertRedirect(route('dashboard'));

        $user = User::where('username', 'legal')->first();
        $this->assertFalse($user->must_change_password);
        $this->get('/')->assertStatus(200);
    }

    public function test_account_locks_after_five_failed_login_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'username' => 'admin',
                'password' => 'WrongPass123',
            ]);
        }

        $admin = User::where('username', 'admin')->first();
        $this->assertNotNull($admin->locked_until);
        $this->assertTrue($admin->locked_until->isFuture());
    }

    public function test_department_cascading_activity_workflow_approval_batch_versions_and_trash(): void
    {
        $deptUser = User::where('username', 'legal')->first();
        $deptUser->update(['must_change_password' => false]);
        $admin = User::where('username', 'admin')->first();

        $year = PlanningYear::where('year', 2027)->first();
        $mo = MainObjective::where('planning_year_id', $year->id)->first();
        $so = SubObjective::where('main_objective_id', $mo->id)->first();
        $pr = Program::where('sub_objective_id', $so->id)->first();

        // 1. Cascading JSON API
        $this->actingAs($deptUser)
            ->getJson("/api/hierarchy/sub-objectives/{$mo->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $so->id]);

        $this->actingAs($deptUser)
            ->getJson("/api/hierarchy/programs/{$so->id}")
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $pr->id]);

        // 2. Department creates activity -> automatic code generated
        $this->actingAs($deptUser)->post('/activities', [
            'main_objective_id' => $mo->id,
            'sub_objective_id' => $so->id,
            'program_id' => $pr->id,
            'title' => 'تحديث العقود النموذجية للبلدية',
            'kpi_name' => 'عدد العقود المحدثة',
            'kpi_target' => '10 عقود',
            'verification_means' => 'نسخ العقود المعتمدة',
            'responsible_entity' => 'الدائرة القانونية',
            'support_type' => 'both',
            'support_internal' => 'الدائرة المالية',
            'support_external' => 'وزارة الحكم المحلي',
            'q1' => '1',
            'q2' => '1',
            'priority' => 'high',
            'estimated_cost' => 9500,
            'funding_source' => 'تمويل ذاتي',
            'save_mode' => 'submit',
        ])->assertRedirect();

        $act = Activity::where('title', 'تحديث العقود النموذجية للبلدية')->firstOrFail();
        $this->assertStringStartsWith($pr->code . '-', $act->activity_code);
        $this->assertEquals('pending', $act->status);
        $this->assertEquals(1, $act->versions()->count());

        // 3. Department updates activity -> creates v2, then restores v1 -> creates v3
        $this->actingAs($deptUser)->put("/activities/{$act->id}", [
            'main_objective_id' => $mo->id,
            'sub_objective_id' => $so->id,
            'program_id' => $pr->id,
            'title' => 'تحديث العقود النموذجية واللوائح',
            'kpi_name' => 'عدد العقود المحدثة',
            'kpi_target' => '15 عقد',
            'verification_means' => 'نسخ العقود المعتمدة',
            'responsible_entity' => 'الدائرة القانونية',
            'support_type' => 'internal',
            'support_internal' => 'الدائرة المالية',
            'q1' => '1',
            'priority' => 'critical',
            'estimated_cost' => 11000,
            'funding_source' => 'تمويل ذاتي',
            'change_summary' => 'زيادة العدد والتكلفة',
        ])->assertRedirect();

        $act->refresh();
        $this->assertEquals(2, $act->current_version);
        $v1 = $act->versions()->where('version_number', 1)->firstOrFail();

        $this->actingAs($deptUser)->post("/activities/{$act->id}/versions/{$v1->id}/restore")->assertRedirect();
        $act->refresh();
        $this->assertEquals(3, $act->current_version);
        $this->assertEquals('10 عقود', $act->kpi_target);

        // 4. Admin rejects with mandatory reason
        $this->actingAs($admin)->post("/activities/{$act->id}/reject", [
            'review_note' => 'يرجى توضيح معيار التحقق القانوني قبل الاعتماد.',
        ])->assertRedirect();
        $this->assertEquals('rejected', $act->fresh()->status);

        // 5. Admin batch approves multiple activities
        $otherAct = Activity::where('planning_year_id', $year->id)->where('id', '!=', $act->id)->first();
        $this->actingAs($admin)->post('/activities/batch', [
            'action' => 'approve',
            'activity_ids' => [$act->id, $otherAct->id],
            'batch_note' => 'اعتماد دفعة واحدة',
        ])->assertRedirect();
        $this->assertEquals('approved', $act->fresh()->status);
        $this->assertEquals('approved', $otherAct->fresh()->status);

        // 6. Entry Period Close blocks department creation
        $this->actingAs($admin)->post("/years/{$year->id}/toggle-entry")->assertRedirect();
        $this->assertFalse($year->fresh()->is_entry_open);
        $this->actingAs($deptUser)->get('/activities/create')->assertRedirect(route('activities.index'));

        // Reopen entry period
        $this->actingAs($admin)->post("/years/{$year->id}/toggle-entry")->assertRedirect();
        $this->assertTrue($year->fresh()->is_entry_open);

        // 7. Attachments upload + Soft delete + Trash restore
        Storage::fake('local');
        $file = UploadedFile::fake()->create('evidence.pdf', 120, 'application/pdf');
        $this->actingAs($deptUser)->post("/activities/{$act->id}/attachments", [
            'title' => 'محضر الاعتماد القانوني',
            'file' => $file,
        ])->assertRedirect();
        $this->assertEquals(1, $act->attachments()->count());

        $this->actingAs($admin)->delete("/activities/{$act->id}")->assertRedirect();
        $this->assertSoftDeleted('activities', ['id' => $act->id]);

        $this->actingAs($admin)->post("/trash/activity/{$act->id}/restore")->assertRedirect();
        $this->assertDatabaseHas('activities', ['id' => $act->id, 'deleted_at' => null]);
    }

    public function test_year_cloning_and_excel_pdf_exports_and_backup(): void
    {
        $admin = User::where('username', 'admin')->first();
        $y2027 = PlanningYear::where('year', 2027)->first();

        // Create 2028 and clone 2027 into 2028
        $this->actingAs($admin)->post('/years', [
            'year' => 2028,
            'title' => 'الخطة التشغيلية 2028',
            'is_entry_open' => '1',
        ])->assertRedirect();
        $y2028 = PlanningYear::where('year', 2028)->firstOrFail();

        $this->actingAs($admin)->post('/years/clone', [
            'source_year_id' => $y2027->id,
            'target_year_id' => $y2028->id,
            'clone_activities' => '1',
            'clone_budgets' => '1',
        ])->assertRedirect();

        $this->assertGreaterThan(0, Activity::where('planning_year_id', $y2028->id)->count());

        // Test Excel exports (official plan + reports)
        foreach (['plan', 'evaluation', 'budgets', 'monthly', 'risks'] as $type) {
            $res = $this->actingAs($admin)->get("/reports/export/excel/{$type}?year_id={$y2027->id}");
            $res->assertStatus(200);
            $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
        }

        // Test Arabic RTL PDF exports (mPDF)
        foreach (['plan', 'evaluation', 'budgets', 'monthly', 'risks'] as $type) {
            $res = $this->actingAs($admin)->get("/reports/export/pdf/{$type}?year_id={$y2027->id}");
            $res->assertStatus(200);
            $this->assertEquals('application/pdf', $res->headers->get('content-type'));
            $this->assertStringStartsWith('%PDF-', $res->getContent());
        }

        // Test Backup Creation
        $backupService = app(BackupService::class);
        $filename = $backupService->createBackup('test_backup');
        $fullPath = $backupService->backupDirectory() . DIRECTORY_SEPARATOR . $filename;
        $this->assertFileExists($fullPath);
        @unlink($fullPath);
    }
}
