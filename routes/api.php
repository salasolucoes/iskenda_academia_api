<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InstructorController;
use App\Http\Controllers\Admin\LiveSessionController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Auth\InstructorAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\Instructor\ChunkedUploadController;
use App\Http\Controllers\Instructor\ContentController;
use App\Http\Controllers\Instructor\DashboardController;
use App\Http\Controllers\Instructor\LessonController;
use App\Http\Controllers\Instructor\ModuleController;
use App\Http\Controllers\Instructor\StudentController as InstructorStudentController;
use App\Http\Controllers\Student\AuthController;
use App\Http\Controllers\Student\CartController;
use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\EnrollmentController;
use App\Http\Controllers\Student\LessonProgressController;
use App\Http\Controllers\Student\LiveSessionController as StudentLiveSessionController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\TicketController;
use App\Http\Controllers\Student\WalletController;
use App\Http\Middleware\ValidateLiveSessionToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:auth');
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])
            ->middleware('throttle:otp-verify');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:auth');

        Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])
            ->middleware('throttle:otp');
        Route::post('/verify-reset-otp', [PasswordResetController::class, 'verifyResetOtp'])
            ->middleware('throttle:otp-verify');
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
            ->middleware('throttle:auth');

        Route::post('/instructor/initiate', [InstructorAuthController::class, 'initiate'])
            ->middleware('throttle:otp');
        Route::post('/instructor/complete', [InstructorAuthController::class, 'complete'])
            ->middleware('throttle:otp-verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::patch('/instructor/set-password', [InstructorAuthController::class, 'setPassword']);
        });
    });

    // Public catalog
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/{course}', [CourseController::class, 'show']);
    Route::get('/categories', [CategoryController::class, 'index']);

    // Public certificate verification
    Route::get('/certificates/{hash}/verify', [CertificateController::class, 'verify']);

    // Public live session join (validates token and redirects)
    Route::get('/live/{liveSessionId}/join', [StudentLiveSessionController::class, 'redirect'])
        ->middleware(['auth:sanctum', ValidateLiveSessionToken::class])
        ->name('live.join');

    // Authenticated routes (without email verification)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });
    });

    // Authenticated student routes (with email verification required)
    Route::middleware(['auth:sanctum', 'email.verified'])->group(function () {

        // Enrollments & Classroom
        Route::get('/enrollments', [EnrollmentController::class, 'index']);
        Route::get('/classroom/{enrollmentId}', [EnrollmentController::class, 'classroom']);
        Route::post('/classroom/{lessonId}/progress', [LessonProgressController::class, 'update'])
            ->middleware('throttle:progress');

        // Wallet
        Route::get('/wallet', [WalletController::class, 'show']);
        Route::post('/wallet/voucher', [WalletController::class, 'uploadVoucher'])
            ->middleware('idempotent');

        // Cart
        Route::get('/cart', [CartController::class, 'show']);
        Route::post('/cart/items', [CartController::class, 'addItem']);
        Route::delete('/cart/items/{id}', [CartController::class, 'removeItem']);
        Route::post('/cart/checkout', [CartController::class, 'checkout'])
            ->middleware('idempotent');

        // Certificates
        Route::post('/certificates/{enrollmentId}/issue', [CertificateController::class, 'issue'])
            ->middleware('throttle:pdf');

        // Live Sessions
        Route::post('/classroom/{liveSessionId}/join', [StudentLiveSessionController::class, 'join'])
            ->middleware('throttle:10,1');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        // Support Tickets
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store'])
            ->middleware('idempotent');
        Route::get('/tickets/{id}', [TicketController::class, 'show']);
        Route::post('/tickets/{id}/messages', [TicketController::class, 'reply']);

        // Instructor routes
        Route::prefix('instructor')->middleware('role:instructor')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index']);
            Route::get('/students', [InstructorStudentController::class, 'index']);

            Route::apiResource('courses', ContentController::class)
                ->parameters(['courses' => 'course']);

            // Modules
            Route::get('/courses/{courseId}/modules', [ModuleController::class, 'index']);
            Route::post('/courses/{courseId}/modules', [ModuleController::class, 'store']);
            Route::put('/modules/{id}', [ModuleController::class, 'update']);
            Route::delete('/modules/{id}', [ModuleController::class, 'destroy']);

            // Lessons
            Route::post('/modules/{moduleId}/lessons', [LessonController::class, 'store']);
            Route::post('/lessons/{id}/upload', [LessonController::class, 'upload'])
                ->middleware('throttle:upload');
            Route::get('/lessons/{id}/preview', [LessonController::class, 'preview']);
            Route::put('/lessons/{id}', [LessonController::class, 'update']);
            Route::delete('/lessons/{id}', [LessonController::class, 'destroy']);

            // Chunked Upload
            Route::post('/upload/init', [ChunkedUploadController::class, 'init'])
                ->middleware('throttle:upload');
            Route::post('/upload/chunk', [ChunkedUploadController::class, 'chunk'])
                ->middleware('throttle:upload');
            Route::post('/upload/{sessionId}/complete', [ChunkedUploadController::class, 'complete'])
                ->middleware('throttle:upload');
            Route::get('/upload/{sessionId}/status', [ChunkedUploadController::class, 'status']);
        });

        // Admin routes
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            // Dashboard
            Route::get('/dashboard', [AdminDashboardController::class, 'index']);

            // Courses
            Route::apiResource('courses', AdminCourseController::class)
                ->parameters(['courses' => 'course']);
            Route::patch('/courses/{course}/publish', [AdminCourseController::class, 'publishToggle']);
            Route::patch('/courses/{course}/instructor', [AdminCourseController::class, 'changeInstructor']);
            Route::get('/lessons/{lessonId}/preview', [AdminCourseController::class, 'lessonPreview']);

            // Categories
            Route::apiResource('categories', AdminCategoryController::class)
                ->parameters(['categories' => 'category']);

            // Users
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
            Route::patch('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
            Route::get('/users/{user}/enrollments', [AdminUserController::class, 'enrollments']);
            Route::get('/users/{user}/wallet', [AdminUserController::class, 'wallet']);
            Route::post('/users/{user}/wallet/adjust', [AdminUserController::class, 'walletAdjust']);
            Route::get('/users/{user}/certificates', [AdminUserController::class, 'certificates']);
            Route::get('/users/{user}/tickets', [AdminUserController::class, 'tickets']);
            Route::get('/users/{user}/progress/{enrollment}', [AdminUserController::class, 'progress']);

            // Instructors
            Route::apiResource('instructors', InstructorController::class)
                ->parameters(['instructors' => 'instructor']);
            Route::patch('/instructors/{instructor}/verify', [InstructorController::class, 'verify']);
            Route::patch('/instructors/{instructor}/unverify', [InstructorController::class, 'unverify']);
            Route::get('/instructors/{instructor}/courses', [InstructorController::class, 'courses']);
            Route::get('/instructors/{instructor}/stats', [InstructorController::class, 'stats']);
            Route::get('/instructors/{instructor}/students', [InstructorController::class, 'students']);
            Route::get('/instructors/{instructor}/live-sessions', [InstructorController::class, 'liveSessions']);

            // Vouchers
            Route::get('/vouchers', [VoucherController::class, 'index']);
            Route::get('/vouchers/{voucher}', [VoucherController::class, 'show']);
            Route::post('/vouchers/{voucher}/approve', [VoucherController::class, 'approve']);
            Route::post('/vouchers/{voucher}/reject', [VoucherController::class, 'reject']);

            // Tickets
            Route::get('/tickets', [AdminTicketController::class, 'index']);
            Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show']);
            Route::patch('/tickets/{ticket}/assign', [AdminTicketController::class, 'assign']);
            Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply']);
            Route::patch('/tickets/{ticket}/close', [AdminTicketController::class, 'close']);

            // Live Sessions
            Route::get('/live-sessions', [LiveSessionController::class, 'index']);
            Route::get('/live-sessions/{live_session}', [LiveSessionController::class, 'show']);
            Route::patch('/live-sessions/{live_session}/force-start', [LiveSessionController::class, 'forceStart']);
            Route::patch('/live-sessions/{live_session}/force-end', [LiveSessionController::class, 'forceEnd']);
            Route::patch('/live-sessions/{live_session}/link', [LiveSessionController::class, 'updateLink']);

            // Notifications
            Route::post('/notifications/broadcast', [AdminNotificationController::class, 'sendBroadcast'])
                ->middleware('throttle:broadcast');
            Route::get('/notifications', [AdminNotificationController::class, 'index']);
        });
    });
});
