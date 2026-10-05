<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Order;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;
use Infrastructure\Persistence\Eloquent\Models\Ticket;
use Infrastructure\Persistence\Eloquent\Models\User;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $data = Cache::tags(['dashboard'])->remember('dashboard', 300, function () {
            return [
                'total_students' => User::where('role', 'student')->count(),
                'total_instructors' => User::where('role', 'instructor')->count(),
                'total_courses' => Course::count(),
                'published_courses' => Course::where('status', 'published')->count(),
                'total_revenue_cents' => Order::where('status', 'completed')->sum('total_cents'),
                'pending_vouchers' => PaymentVoucher::where('status', 'pending')->count(),
                'open_tickets' => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
                'active_enrollments' => Enrollment::where('status', 'active')->count(),
            ];
        });

        return response()->json(['data' => $data]);
    }
}
