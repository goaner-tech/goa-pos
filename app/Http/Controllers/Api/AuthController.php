<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required', 'string', 'digits_between:4,8'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        $employee = Employee::findByPin($data['pin']);
        if (! $employee) {
            throw ValidationException::withMessages(['pin' => 'Wrong PIN.']);
        }

        $token = $employee->createToken($data['device'] ?? 'terminal')->plainTextToken;

        return response()->json(['token' => $token, 'employee' => $this->present($employee)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['employee' => $this->present($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function present(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'role' => $employee->role,
            'can_manage' => $employee->role->canManage(),
            'can_take_orders' => $employee->role->canTakeOrders(),
        ];
    }
}
