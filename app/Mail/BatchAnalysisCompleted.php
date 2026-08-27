<?php

namespace App\Mail;

use App\Models\VoiceAnalysisBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BatchAnalysisCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VoiceAnalysisBatch $batch)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Voice analysis complete: {$this->batch->original_filename}",
        );
    }

    public function content(): Content
    {
        $analyses = $this->batch->analyses;

        return new Content(
            markdown: 'emails.batch-analysis-completed',
            with: [
                'batch' => $this->batch,
                'total' => $analyses->count(),
                'completed' => $analyses->where('status', 'completed')->count(),
                'failed' => $analyses->where('status', 'failed'),
            ],
        );
    }
}
