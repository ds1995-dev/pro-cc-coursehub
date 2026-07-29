<?php

namespace App\Http\Controllers;

use App\Models\User;

class AdminStudentController extends Controller
{
    public function index()
    {
        // N+1 回避: 件数のみ使う enrollments は withCount で集計
        $students = User::where('role', 'student')
            ->withCount('enrollments')
            ->paginate(20);

        return view('admin.students.index', compact('students'));
    }
}
