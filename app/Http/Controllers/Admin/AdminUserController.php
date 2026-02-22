<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with(['contacts', 'roles'])
            ->withCount(['contacts'])
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%")
                  ->orWhereHas('contacts', fn($c) => $c->where('value', 'like', "%{$search}%"));
            });
        }

        if ($role = $request->input('role')) {
            $query->role($role);
        }

        $users = $query->paginate(25)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function destroy(Request $request, User $user)
    {
        // Prevent deleting yourself or other super admins
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole('super_admin')) {
            return back()->with('error', 'Super admin accounts cannot be deleted.');
        }

        $name = $user->name;

        // Get student IDs for cascade
        $studentIds = DB::table('registration_students')
            ->where('user_id', $user->id)
            ->pluck('id');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('user_contacts')->where('user_id', $user->id)->delete();
        DB::table('model_has_roles')->where('model_id', $user->id)->where('model_type', User::class)->delete();
        DB::table('model_has_permissions')->where('model_id', $user->id)->where('model_type', User::class)->delete();
        if ($studentIds->isNotEmpty()) {
            DB::table('course_enrollments')->whereIn('student_id', $studentIds)->delete();
        }
        DB::table('registration_students')->where('user_id', $user->id)->delete();
        DB::table('payments')->where('user_id', $user->id)->delete();
        $user->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        return back()->with('success', "User \"{$name}\" has been deleted.");
    }
}
