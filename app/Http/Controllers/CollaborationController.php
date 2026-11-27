<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\InternalMessage;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CollaborationController extends Controller
{
    public function notifications(): View
    {
        $notifications = SystemNotification::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('collaboration.notifications', compact('notifications'));
    }

    public function markNotificationRead(SystemNotification $notification): RedirectResponse
    {
        if ($notification->user_id !== Auth::id()) {
            abort(403);
        }
        $notification->update(['is_read' => true]);
        if ($notification->link_url) {
            return redirect($notification->link_url);
        }
        return back();
    }

    public function markAllNotificationsRead(): RedirectResponse
    {
        SystemNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);
        return back()->with('success', 'تم تحديد جميع الإشعارات كمقروءة.');
    }

    public function messages(Request $request): View
    {
        $user = Auth::user();
        $tab = $request->input('tab', 'inbox');

        $inbox = InternalMessage::with(['sender.department', 'recipientDepartment', 'recipientUser'])
            ->where(function ($q) use ($user) {
                $q->where('recipient_user_id', $user->id);
                if ($user->department_id) {
                    $q->orWhere('recipient_department_id', $user->department_id);
                }
            })
            ->orderByDesc('created_at')
            ->get();

        $sent = InternalMessage::with(['recipientDepartment', 'recipientUser'])
            ->where('sender_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $departments = Department::orderBy('code')->get();
        $admins = User::where('role', 'admin')->where('is_active', true)->get();

        return view('collaboration.messages', compact('tab', 'inbox', 'sent', 'departments', 'admins'));
    }

    public function sendMessage(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'recipient_department_id' => ['nullable', 'string'],
            'recipient_user_id' => ['nullable', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:250'],
            'body' => ['required', 'string', 'max:5000'],
            'parent_message_id' => ['nullable', 'exists:internal_messages,id'],
        ], [
            'subject.required' => 'يرجى كتابة موضوع الرسالة.',
            'body.required' => 'يرجى كتابة نص الرسالة.',
        ]);

        // Support Admin broadcast to ALL departments
        if ($user->isAdmin() && ($validated['recipient_department_id'] ?? '') === 'all') {
            $depts = Department::all();
            foreach ($depts as $dept) {
                $msg = InternalMessage::create([
                    'sender_id' => $user->id,
                    'recipient_department_id' => $dept->id,
                    'subject' => trim($validated['subject']),
                    'body' => trim($validated['body']),
                    'is_read' => false,
                ]);
                AuditService::notifyDepartment(
                    $dept->id,
                    'تعميم داخلي جديد من المشرف العام',
                    "{$msg->subject}",
                    'message',
                    route('messages.index'),
                    $user->id
                );
            }
            AuditService::log('create', 'internal_message', null, 'تعميم لجميع الدوائر: ' . trim($validated['subject']));
            return redirect()->route('messages.index', ['tab' => 'sent'])->with('success', "تم إرسال التعميم الداخلي إلى جميع الدوائر ({$depts->count()} دائرة) بنجاح.");
        }

        $recipientDeptId = is_numeric($validated['recipient_department_id'] ?? null)
            ? (int) $validated['recipient_department_id']
            : null;
        $recipientUserId = ($validated['recipient_user_id'] ?? null) ?: null;

        if (!$recipientDeptId && !$recipientUserId) {
            if ($user->isDepartment()) {
                $firstAdmin = User::where('role', 'admin')->where('is_active', true)->first();
                $recipientUserId = $firstAdmin?->id;
            } else {
                return back()->withErrors(['recipient_department_id' => 'يرجى تحديد الدائرة أو المستلم.']);
            }
        }

        $msg = InternalMessage::create([
            'sender_id' => $user->id,
            'recipient_user_id' => $recipientUserId,
            'recipient_department_id' => $recipientDeptId,
            'subject' => trim($validated['subject']),
            'body' => trim($validated['body']),
            'parent_message_id' => ($validated['parent_message_id'] ?? null) ?: null,
            'is_read' => false,
        ]);

        if ($recipientDeptId) {
            AuditService::notifyDepartment(
                (int) $recipientDeptId,
                'رسالة داخلية جديدة',
                "رسالة من {$user->name}: {$msg->subject}",
                'message',
                route('messages.index'),
                $user->id
            );
        } elseif ($recipientUserId) {
            AuditService::notifyUser(
                (int) $recipientUserId,
                'رسالة داخلية جديدة',
                "رسالة من {$user->name}: {$msg->subject}",
                'message',
                route('messages.index')
            );
        }

        AuditService::log('create', 'internal_message', $msg->id, $msg->subject);
        return redirect()->route('messages.index', ['tab' => 'sent'])->with('success', 'تم إرسال الرسالة الداخلية بنجاح.');
    }

    public function markMessageRead(InternalMessage $message): RedirectResponse
    {
        $user = Auth::user();
        $isRecipient = ($message->recipient_user_id === $user->id)
            || ($user->department_id && $message->recipient_department_id === $user->department_id);
        if (!$isRecipient && !$user->isAdmin()) {
            abort(403);
        }
        $message->update(['is_read' => true, 'read_at' => now()]);
        return back()->with('success', 'تم تعليم الرسالة كمقروءة.');
    }

    public function deleteMessage(InternalMessage $message): RedirectResponse
    {
        $user = Auth::user();
        $canDelete = $user->isAdmin()
            || $message->sender_id === $user->id
            || $message->recipient_user_id === $user->id
            || ($user->department_id && $message->recipient_department_id === $user->department_id);
        if (!$canDelete) {
            abort(403);
        }
        $message->update(['deleted_by_id' => $user->id]);
        $message->delete();
        return back()->with('success', 'تم نقل الرسالة إلى سلة المحذوفات.');
    }
}
