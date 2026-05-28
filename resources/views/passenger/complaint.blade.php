@extends('layouts.dashboard')

@section('panel')
<h1>Submit Complaint</h1>
<div class="kbs-card kbs-form">
    <form method="POST" action="{{ route('passenger.complaints.store') }}">
        @csrf
        <label>Subject</label>
        <input name="subject" required>
        <label>Message</label>
        <textarea name="message" rows="5" required></textarea>
        <button class="kbs-btn kbs-btn-primary">Submit</button>
    </form>
</div>
@endsection
