<?php

namespace App\Http\Controllers\Web;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminWebController extends Controller
{
    public function dashboard(): View
    {
        $stats = [
            'passengers' => User::where('role', UserRole::Passenger->value)->count(),
            'operators' => User::where('role', UserRole::Operator->value)->count(),
            'drivers' => User::where('role', UserRole::Driver->value)->count(),
            'bookings_today' => Booking::whereDate('created_at', today())->count(),
            'revenue' => Payment::where('status', 'successful')->sum('amount'),
            'active_trips' => Schedule::where('status', 'in_progress')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    public function users(Request $request): View
    {
        $users = User::when($request->role, fn ($q, $r) => $q->where('role', $r))->latest()->paginate(25);

        return view('admin.users', compact('users'));
    }

    public function createUser(): View
    {
        $roles = UserRole::cases();

        return view('admin.users.create', compact('roles'));
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'is_active' => ['boolean'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
            'role' => UserRole::from($data['role']),
            'is_active' => $data['is_active'] ?? false,
        ]);

        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function editUser(User $user): View
    {
        $roles = UserRole::cases();

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20', 'unique:users,phone,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', 'in:' . implode(',', array_column(UserRole::cases(), 'value'))],
            'is_active' => ['boolean'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => UserRole::from($data['role']),
            'is_active' => $data['is_active'] ?? $user->is_active,
            'password' => isset($data['password']) ? Hash::make($data['password']) : $user->password,
        ]);

        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    public function deleteUser(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    public function buses(): View
    {
        $buses = Bus::with('operator')->latest()->paginate(25);
        $operators = User::where('role', UserRole::Operator->value)->get();

        return view('admin.buses.index', compact('buses', 'operators'));
    }

    public function createBus(): View
    {
        $operators = User::where('role', UserRole::Operator)->get();

        return view('admin.buses.create', compact('roles'));
    }

    public function storeBus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plate_number' => ['required', 'unique:buses,plate_number'],
            'fleet_number' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'rows' => ['required', 'integer', 'min:1'],
            'seats_per_row' => ['required', 'integer', 'min:1'],
            'model' => ['nullable', 'string'],
            'operator_id' => ['required', 'exists:users,id'],
        ]);

        $operator = User::where('id', $data['operator_id'])->where('role', UserRole::Operator->value)->first();
        if (! $operator) {
            return back()->withErrors(['operator_id' => 'Selected user is not a valid operator.'])->withInput();
        }

        Bus::create($data);

        return redirect()->route('admin.buses')->with('success', 'Bus created successfully.');
    }

    public function editBus(Bus $bus): View
    {
        $operators = User::where('role', UserRole::Operator->value)->get();

        return view('admin.buses.edit', compact('bus', 'operators'));
    }

    public function updateBus(Request $request, Bus $bus): RedirectResponse
    {
        $data = $request->validate([
            'plate_number' => ['required', 'unique:buses,plate_number,' . $bus->id],
            'fleet_number' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'rows' => ['required', 'integer', 'min:1'],
            'seats_per_row' => ['required', 'integer', 'min:1'],
            'model' => ['nullable', 'string'],
            'operator_id' => ['required', 'exists:users,id'],
        ]);

        $operator = User::where('id', $data['operator_id'])->where('role', UserRole::Operator->value)->first();
        if (! $operator) {
            return back()->withErrors(['operator_id' => 'Selected user is not a valid operator.'])->withInput();
        }

        $bus->update($data);

        return redirect()->route('admin.buses')->with('success', 'Bus updated successfully.');
    }

    public function deleteBus(Bus $bus): RedirectResponse
    {
        $bus->delete();

        return back()->with('success', 'Bus deleted successfully.');
    }

    public function approveOperator(User $user): RedirectResponse
    {
        $user->update(['operator_approved_at' => now()]);

        return back()->with('success', 'Operator approved.');
    }

    public function monitor(): View
    {
        $trips = Schedule::with(['bus', 'route', 'latestLocation', 'driver'])
            ->whereIn('status', ['in_progress', 'boarding', 'delayed'])
            ->whereDate('travel_date', today())
            ->get();

        return view('admin.monitor', compact('trips'));
    }

    public function payments(): View
    {
        $payments = Payment::with('booking.user')->latest()->paginate(25);

        return view('admin.payments', compact('payments'));
    }

    public function complaints(): View
    {
        $complaints = Complaint::with('user')->latest()->paginate(20);

        return view('admin.complaints', compact('complaints'));
    }

    public function resolveComplaint(Request $request, Complaint $complaint): RedirectResponse
    {
        $data = $request->validate(['admin_response' => ['required', 'string']]);

        $complaint->update([
            ...$data,
            'status' => 'resolved',
            'handled_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', 'Complaint resolved.');
    }
}
