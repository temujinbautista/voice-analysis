<x-mail::message>
# Voice analysis complete

**Batch:** {{ $batch->original_filename }}<br>
**Uploaded:** {{ $batch->created_at->format('M j, Y g:i A') }}

**{{ $completed }} of {{ $total }}** file(s) completed successfully.

@if ($failed->isNotEmpty())
**{{ $failed->count() }} file(s) failed:**

<x-mail::table>
| File | Error |
| :--- | :--- |
@foreach ($failed as $analysis)
| {{ $analysis->file_name }} | {{ Str::limit($analysis->error, 80) }} |
@endforeach
</x-mail::table>
@endif

Log in to the dashboard to review the full results.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
