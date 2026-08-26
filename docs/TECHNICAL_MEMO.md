# Technical Memo: Voice Tone / Background Noise Dashboard

Temujin Bautista, 2026-08-27

Scope: classifying call-center audio for emotional tone/intensity, background noise, audio quality, speaker overlap, and long silence, plus the batch upload workflow, cost/latency numbers, and validation against the 3 labeled samples from the brief.

## Approach

Two things run on every uploaded call, not one.

Gemini's `gemini-3.1-flash-lite` handles the judgment calls: `emotional_tone`, `emotional_intensity`, `background_noise_present/type/severity`, `audio_quality`, `speaker_overlap_present`, and a `confidence` score, using structured JSON output (`responseSchema`) so there's no parsing guesswork. `gemini-3.5-flash-lite` is wired up as an automatic fallback if the primary model errors out or gets rate-limited (more on why that needed to be visible in section 7).

`long_silence_present` is handled separately, by ffmpeg's `silencedetect` filter running directly on the waveform. That field started out as another thing the LLM guessed at, but its guesses were unreliable and there was no good way to sanity-check them short of listening to the whole file. ffmpeg gives an exact answer for free. This is really the only field where a deterministic approach was a clear win over asking the model — section 8 gets into why the rest stayed LLM-based.

Both pieces get merged into one result row per file. Uploads can be a single audio file or a zip batch with a CSV manifest, and each file is queued and processed independently, so a batch's total time depends on how many workers are running, not how many files are in it.

## Validation results

Ground truth is the 3 samples that shipped with the brief: call_001 is upset/high, call_002 is neutral/medium, call_003 is satisfied/medium. What's below is a straight run of the current code and prompt against all three, not a cherry-picked run.

| File | Field | Predicted | Expected | Match |
|---|---|---|---|---|
| call_001 (30.9s) | emotional_tone | neutral | upset | no |
| | emotional_intensity | low | high | no |
| | background_noise_present | false | false | yes |
| | background_noise_severity | none | none | yes |
| | audio_quality | clear | clear | yes |
| | speaker_overlap_present | false | false | yes |
| | long_silence_present (DSP) | false | false | yes |
| call_002 (35.0s) | emotional_tone | neutral | neutral | yes |
| | emotional_intensity | low | medium | no |
| | background_noise_present | true | true | yes |
| | background_noise_severity | low | medium | no |
| | audio_quality | clear | clear | yes |
| | speaker_overlap_present | false | true | no |
| | long_silence_present (DSP) | false | false | yes |
| call_003 (171.9s) | emotional_tone | neutral | satisfied | no |
| | emotional_intensity | low | medium | no |
| | background_noise_present | false | true | no |
| | background_noise_severity | none | medium | no |
| | audio_quality | clear | clear | yes |
| | speaker_overlap_present | true | true | yes |
| | long_silence_present (DSP) | false | false | yes |

Field accuracy across the 21 predictions:

| Field | Accuracy |
|---|---|
| emotional_tone | 1/3 |
| emotional_intensity | 0/3 |
| background_noise_present | 2/3 |
| background_noise_severity | 1/3 |
| audio_quality | 3/3 |
| speaker_overlap_present | 2/3 |
| long_silence_present (DSP) | 3/3 |
| Overall | 12/21 |

Confusion matrix for emotional_tone (rows are actual, columns predicted):

| Actual \\ Predicted | neutral | satisfied | frustrated | upset | distressed |
|---|---|---|---|---|---|
| neutral | 1 | | | | |
| satisfied | 1 | | | | |
| upset | 1 | | | | |

Worth noting: across every prompt and model variant tried during this trial, the model never once predicted the wrong non-neutral label. Its only failure mode is collapsing everything toward neutral/low. That's a precision-safe but recall-poor kind of wrong — it'll miss real frustration or satisfaction more than it'll false-alarm on a calm call, which is a very different risk profile than random noise in the predictions.

## Confidence is not calibrated

The clearest piece of evidence for this: on call_001, the model reported 0.95 confidence and got both emotional_tone and emotional_intensity wrong. Confidence sat in a tight 0.90–0.95 band across all three calls regardless of whether the answer was right, which means right now the confidence field tells you almost nothing about whether to trust the prediction. It shouldn't be used for anything downstream (auto-escalation, routing to review) until it's actually calibrated against a real validation set — see the next-steps section.

## Reproducibility isn't perfect either

Even at temperature 0.2, running the same audio through the same model twice doesn't guarantee the same output. A prompt-variant test done earlier in this trial and the run captured above disagreed on call_002 and call_003's exact field predictions, despite testing the same underlying idea. This is just how hosted LLM inference behaves, but it matters here: any reproducibility claim needs to be about accuracy over N runs, not about one run being repeatable. We didn't average over repeated runs for the table above, given the free-tier request budget, but that's a real gap worth naming rather than hiding.

## Cost

Standard-tier published pricing, no free-tier discount factored in (so this is the worst case): gemini-3.1-flash-lite runs $0.25 / $1.50 per 1M text input/output tokens, and $0.50 per 1M audio input tokens.

Measured cost per audio-minute on the actual sample calls:

| Clip | Duration | Cost/min |
|---|---|---|
| call_001 | 30.9s | ~$0.00117 |
| call_002 | 35.0s | ~$0.00114 |
| call_003 | 171.9s | ~$0.00083 |
| Average | | ~$0.0009/min |

That's 2.5-3.75x under the $0.003/minute ceiling with no discount applied. Cost per minute drops on longer calls since the fixed per-request prompt/output overhead gets spread over more audio. The DSP step adds nothing to the API bill since it's a local ffmpeg pass.

## Latency

End-to-end, Gemini call plus DSP, current code:

| Clip | Duration | Latency |
|---|---|---|
| call_001 | 30.9s | 4.33s |
| call_002 | 35.0s | 5.45s |
| call_003 | 171.9s | 6.28s |
| Average | | ~5.35s |

Latency barely moves with clip length here — a call almost 6x longer only added about 2 seconds. It's dominated by the Gemini round trip, not audio length, and ffmpeg's part of the job finishes in well under a second locally. Since each file is its own queued job, batch throughput is really a function of worker count, not file count.

## Making fallback substitutions visible

Partway through this trial, a real rate-limit hit on the primary model caused a silent fallback to the secondary one, and the dashboard had no way to show that had happened — the result just looked like a normal answer from a better model. That got fixed by storing which model actually produced each result and showing a fallback badge whenever it wasn't the configured primary. Worth calling out on its own because without it, a quota exhaustion event degrades quality invisibly, which is a worse failure than an obvious error.

## Why not lean on DSP more

The brief is explicit that acoustic/signal-processing approaches are fair game and an LLM isn't required, so this was tested rather than assumed. DSP is a clean win for long-silence detection: deterministic, free, and more accurate than the model's guess. It doesn't hold up the same way for background noise type/severity or speaker overlap, since those involve judgment calls (is this noise "meaningful," is the overlap "enough to matter") that a threshold tuned against 3 labeled examples would almost certainly overfit rather than generalize. Emotional tone is an even worse candidate for a hand-built classifier for the same reason, it's a more subjective and higher-dimensional call than silence detection, and 3 examples isn't enough to fit or validate anything against. That's why nothing further was built there for this pass, not because it isn't allowed.

## Limitations and what's actually next

Tone and intensity accuracy (1/3 and 0/3) are the weak spots here, and it's worth being upfront about it rather than dressing it up. The failure mode is at least consistent, under-calling intensity and defaulting to neutral, which is useful to know but doesn't close the gap on its own.

One lever that was tried directly: refining the prompt to call out paralinguistic cues (pace, tension, sighing) and explicitly discourage defaulting to neutral. It helped intensity calibration in isolation but didn't move the needle on call_001 specifically, and shifted tone predictions on the other two calls in ways that weren't obviously better. That reads less like a prompt-wording issue and more like something closer to a real ceiling on what the Lite tier picks up from these particular clips.

Deliberately not doing: further hand-tuning the prompt or any thresholds against these same 3 samples. With only 3 labeled examples, doing that would just be fitting to the training set, which is exactly what the brief warns against. Any change that happens to fix call_001 without something held out to check it against isn't really a fix, it's an illusion of one.

If there were more labeled data to work with, the next moves in rough priority order:

1. Calibrate confidence properly (temperature scaling or isotonic regression against a real validation set), then use it to route only the genuinely uncertain calls to something like the pricier gemini-3.6-flash, so the expensive model is used sparingly and the cost ceiling still holds.
2. Add a lightweight prosodic/acoustic classifier (pitch, energy, speaking rate, pauses) as a second vote alongside the LLM, and flag disagreements for review instead of trusting either signal blindly.
3. Grow the labeled set before touching the prompt or thresholds again. 3 examples can't really support any accuracy claim in either direction, the numbers above are directional, not a real estimate of production accuracy.

## Out of scope for this pass

Public hosting/deployment was explicitly set aside for now. Everything above was run and validated locally end to end, real queue worker, real ffmpeg pass, real Gemini API calls, nothing mocked.
