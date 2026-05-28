@extends('layouts.dashboard')

@section('title', 'Manage Buses')

@section('panel')
    <h1>Manage Buses</h1>

    <div class="kbs-card" style="margin-bottom: 1.5rem;">
        <a href="{{ route('admin.buses.create') }}" class="kbs-btn kbs-btn-primary">Add New Bus</a>
    </div>

    <div class="kbs-card">
        <table class="kbs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Plate Number</th>
                    <th>Model</th>
                    <th>Capacity</th>
                    <th>Operator</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($buses as $bus)
                    <tr>
                        <td>{{ $bus->id }}</td>
                        <td>{{ $bus->plate_number }}</td>
                        <td>{{ $bus->model ?? 'N/A' }}</td>
                        <td>{{ $bus->capacity }}</td>
                        <td>{{ $bus->operator->name ?? 'N/A' }}</td>
                        <td>
                            <a href="{{ route('admin.buses.edit', $bus) }}" class="kbs-btn kbs-btn-sm kbs-btn-info">Edit</a>
                            <form action="{{ route('admin.buses.delete', $bus) }}" method="POST" style="display:inline-block;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="kbs-btn kbs-btn-sm kbs-btn-danger" onclick="return confirm('Are you sure you want to delete this bus?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No buses found.</td></tr>
                @endforelse
            </tbody>
        </table>
        {{ $buses->links() }}
    </div>
@endsection
