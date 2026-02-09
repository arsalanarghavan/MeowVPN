<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Jobs\SendTelegramNotification;
use App\Models\User;

class TicketController extends Controller
{
    /**
     * List tickets (users see their own, admins see all)
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::with(['user', 'assignee', 'latestMessage']);

        if ($user->isAdmin()) {
            // Admins can filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('priority')) {
                $query->where('priority', $request->priority);
            }
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        } else {
            // Users only see their own tickets
            $query->where('user_id', $user->id);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * Show ticket with messages
     */
    public function show(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        // Check authorization
        if (!$user->isAdmin() && $ticket->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json(
            $ticket->load(['user', 'assignee', 'messages.user'])
        );
    }

    /**
     * Create new ticket
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'department' => 'nullable|string|max:50',
        ]);

        $ticket = Ticket::create([
            'user_id' => $request->user()->id,
            'subject' => $data['subject'],
            'status' => 'open',
            'priority' => $data['priority'] ?? 'medium',
            'department' => $data['department'] ?? 'support',
        ]);

        // Create first message
        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_staff_reply' => false,
        ]);

        // Notify admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            if ($admin->telegram_id) {
                SendTelegramNotification::dispatch(
                    $admin->telegram_id,
                    "🎫 تیکت جدید #{$ticket->id}\n\n" .
                    "👤 کاربر: {$request->user()->username}\n" .
                    "📝 موضوع: {$ticket->subject}\n" .
                    "⚡ اولویت: {$ticket->priority}"
                );
            }
        }

        return response()->json(
            $ticket->load(['messages.user']),
            201
        );
    }

    /**
     * Reply to ticket
     */
    public function reply(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        // Check authorization
        if (!$user->isAdmin() && $ticket->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'message' => 'required|string',
        ]);

        $isStaff = $user->isAdmin();

        $message = TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $data['message'],
            'is_staff_reply' => $isStaff,
        ]);

        // Update ticket status
        if ($isStaff) {
            $ticket->update(['status' => 'answered']);
            
            // Notify user
            $ticketOwner = $ticket->user;
            if ($ticketOwner->telegram_id) {
                SendTelegramNotification::dispatch(
                    $ticketOwner->telegram_id,
                    "📩 پاسخ جدید به تیکت #{$ticket->id}\n\n" .
                    "📝 موضوع: {$ticket->subject}\n\n" .
                    "برای مشاهده پاسخ به پنل خود مراجعه کنید."
                );
            }
        } else {
            $ticket->update(['status' => 'pending']);

            // Notify admins
            if ($ticket->assigned_to) {
                $assignee = $ticket->assignee;
                if ($assignee && $assignee->telegram_id) {
                    SendTelegramNotification::dispatch(
                        $assignee->telegram_id,
                        "📩 پاسخ جدید کاربر به تیکت #{$ticket->id}\n\n" .
                        "👤 کاربر: {$user->username}\n" .
                        "📝 موضوع: {$ticket->subject}"
                    );
                }
            }
        }

        return response()->json($message->load('user'));
    }

    /**
     * Close ticket
     */
    public function close(Request $request, Ticket $ticket)
    {
        $user = $request->user();

        // Check authorization
        if (!$user->isAdmin() && $ticket->user_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $ticket->update(['status' => 'closed']);

        return response()->json([
            'message' => 'تیکت با موفقیت بسته شد',
            'ticket' => $ticket,
        ]);
    }

    /**
     * Reopen ticket (admin only)
     */
    public function reopen(Request $request, Ticket $ticket)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }
        $ticket->update(['status' => 'open']);

        return response()->json([
            'message' => 'تیکت مجدداً باز شد',
            'ticket' => $ticket,
        ]);
    }

    /**
     * Assign ticket to admin
     */
    public function assign(Request $request, Ticket $ticket)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }
        $data = $request->validate([
            'admin_id' => 'required|exists:users,id',
        ]);

        $admin = User::findOrFail($data['admin_id']);

        if (!$admin->isAdmin()) {
            return response()->json(['error' => 'کاربر انتخاب شده ادمین نیست'], 400);
        }

        $ticket->update(['assigned_to' => $admin->id]);

        // Notify assigned admin
        if ($admin->telegram_id) {
            SendTelegramNotification::dispatch(
                $admin->telegram_id,
                "🎫 تیکت #{$ticket->id} به شما اختصاص یافت\n\n" .
                "👤 کاربر: {$ticket->user->username}\n" .
                "📝 موضوع: {$ticket->subject}"
            );
        }

        return response()->json([
            'message' => 'تیکت با موفقیت اختصاص یافت',
            'ticket' => $ticket->load('assignee'),
        ]);
    }

    /**
     * Update ticket priority (admin only)
     */
    public function updatePriority(Request $request, Ticket $ticket)
    {
        if (!$request->user()->isAdmin()) {
            abort(403);
        }
        $data = $request->validate([
            'priority' => 'required|in:low,medium,high,urgent',
        ]);

        $ticket->update(['priority' => $data['priority']]);

        return response()->json([
            'message' => 'اولویت تیکت بروزرسانی شد',
            'ticket' => $ticket,
        ]);
    }

    /**
     * Get ticket stats (admin only)
     */
    public function stats()
    {
        return response()->json([
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'pending' => Ticket::where('status', 'pending')->count(),
            'answered' => Ticket::where('status', 'answered')->count(),
            'closed' => Ticket::where('status', 'closed')->count(),
            'by_priority' => [
                'urgent' => Ticket::where('priority', 'urgent')->whereIn('status', ['open', 'pending'])->count(),
                'high' => Ticket::where('priority', 'high')->whereIn('status', ['open', 'pending'])->count(),
                'medium' => Ticket::where('priority', 'medium')->whereIn('status', ['open', 'pending'])->count(),
                'low' => Ticket::where('priority', 'low')->whereIn('status', ['open', 'pending'])->count(),
            ],
        ]);
    }
}

