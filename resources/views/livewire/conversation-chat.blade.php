<div class="rfx-chat" wire:poll.5s>
    <style>
        .rfx-chat { --rfx-border: rgb(229 231 235); --rfx-bg: rgb(243 244 246); --rfx-in: #fff; --rfx-out: rgb(220 248 198); --rfx-agent: rgb(219 234 254); --rfx-muted: rgb(107 114 128); --rfx-text: rgb(17 24 39); --rfx-input: #fff; }
        .dark .rfx-chat { --rfx-border: rgb(255 255 255 / .1); --rfx-bg: rgb(17 24 39); --rfx-in: rgb(31 41 55); --rfx-out: rgb(6 78 59); --rfx-agent: rgb(30 58 138); --rfx-muted: rgb(156 163 175); --rfx-text: rgb(243 244 246); --rfx-input: rgb(31 41 55); }
        .rfx-chat { display: flex; flex-direction: column; border: 1px solid var(--rfx-border); border-radius: .75rem; overflow: hidden; color: var(--rfx-text); }
        .rfx-chat__log { display: flex; flex-direction: column; gap: .5rem; height: 32rem; max-height: 65vh; overflow-y: auto; padding: 1rem; background: var(--rfx-bg); }
        .rfx-chat__older { align-self: center; font-size: .75rem; color: var(--rfx-muted); text-decoration: underline; background: none; border: 0; cursor: pointer; }
        .rfx-chat__day { align-self: center; font-size: .7rem; color: var(--rfx-muted); padding: .125rem .5rem; border-radius: 999px; background: var(--rfx-in); }
        .rfx-msg { max-width: min(36rem, 85%); padding: .5rem .75rem; border-radius: .75rem; font-size: .875rem; line-height: 1.35; box-shadow: 0 1px 1px rgb(0 0 0 / .06); overflow-wrap: anywhere; }
        .rfx-msg__text { display: block; white-space: pre-wrap; }
        .rfx-msg--in { align-self: flex-start; background: var(--rfx-in); border-top-left-radius: .2rem; }
        .rfx-msg--out { align-self: flex-end; background: var(--rfx-out); border-top-right-radius: .2rem; }
        .rfx-msg--agent { background: var(--rfx-agent); }
        .rfx-msg__who { display: block; font-size: .7rem; font-weight: 600; color: var(--rfx-muted); margin-bottom: .125rem; }
        .rfx-msg__meta { display: block; text-align: right; font-size: .65rem; color: var(--rfx-muted); margin-top: .25rem; }
        .rfx-msg__meta--failed { color: rgb(220 38 38); font-weight: 600; }
        .rfx-msg__tag { display: inline-block; font-size: .7rem; padding: 0 .375rem; border-radius: .25rem; background: rgb(0 0 0 / .06); margin-bottom: .25rem; }
        .rfx-msg__buttons { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .375rem; }
        .rfx-msg__buttons span { font-size: .7rem; padding: .125rem .5rem; border: 1px solid var(--rfx-border); border-radius: 999px; }
        .rfx-chat__empty { margin: auto; color: var(--rfx-muted); font-size: .875rem; }
        .rfx-chat__composer { display: flex; gap: .5rem; align-items: flex-end; padding: .75rem; border-top: 1px solid var(--rfx-border); }
        .rfx-chat__composer textarea { flex: 1; resize: vertical; min-height: 2.75rem; max-height: 10rem; padding: .5rem .75rem; border: 1px solid var(--rfx-border); border-radius: .5rem; background: var(--rfx-input); color: var(--rfx-text); font-size: .875rem; }
        .rfx-chat__notice { padding: .75rem; font-size: .8rem; color: var(--rfx-muted); border-top: 1px solid var(--rfx-border); }
    </style>

    <div
        class="rfx-chat__log"
        x-data="{ bottom() { this.$el.scrollTop = this.$el.scrollHeight } }"
        x-init="
            bottom();
            {{-- Si el agente está cerca del final, seguir los mensajes nuevos. --}}
            let near = true;
            $el.addEventListener('scroll', () => near = $el.scrollHeight - $el.scrollTop - $el.clientHeight < 120);
            new MutationObserver(() => { if (near) bottom() }).observe($el, { childList: true, subtree: true });
        "
        x-on:rfx-chat-sent.window="setTimeout(() => bottom(), 50)"
    >
        @if ($hasOlder)
            <button type="button" class="rfx-chat__older" wire:click="loadOlder">{{ __('admin.resources.conversations.chat.load_older') }}</button>
        @endif

        @php($lastDay = null)
        @forelse ($messages as $message)
            @php($day = $message->created_at?->timezone(config('app.timezone'))->translatedFormat('j M Y'))
            @if ($day !== $lastDay)
                <span class="rfx-chat__day">{{ $day }}</span>
                @php($lastDay = $day)
            @endif

            @php($isOut = $message->direction === 'outbound')
            @php($isAgent = $isOut && $message->sent_by_user_id !== null)
            <div wire:key="msg-{{ $message->id }}" @class(['rfx-msg', 'rfx-msg--in' => ! $isOut, 'rfx-msg--out' => $isOut, 'rfx-msg--agent' => $isAgent])>
                @if ($isOut)
                    <span class="rfx-msg__who">
                        {{ $isAgent ? ($message->sentBy?->name ?? __('admin.resources.conversations.chat.agent')) : __('admin.resources.conversations.chat.bot') }}
                    </span>
                @endif

                @if (in_array($message->message_type, ['image', 'document', 'audio', 'video', 'template'], true))
                    <span class="rfx-msg__tag">{{ __('admin.resources.conversations.chat.types.'.$message->message_type) }}</span>
                    @if ($link = data_get($message->payload_json, 'document.link'))
                        <a href="{{ $link }}" target="_blank" rel="noopener" style="text-decoration: underline">{{ data_get($message->payload_json, 'document.filename', $link) }}</a>
                    @endif
                @endif

                @if (filled($message->body_text))
                    <span class="rfx-msg__text">{{ $message->body_text }}</span>
                @elseif (! in_array($message->message_type, ['image', 'document', 'audio', 'video', 'template'], true))
                    <em>{{ __('admin.resources.conversations.chat.no_text') }}</em>
                @endif

                @if ($buttons = data_get($message->payload_json, 'interactive_buttons'))
                    <div class="rfx-msg__buttons">
                        @foreach ($buttons as $button)
                            <span>{{ $button['title'] ?? '' }}</span>
                        @endforeach
                    </div>
                @endif

                @php($status = $message->provider_status ?: $message->status)
                <span @class(['rfx-msg__meta', 'rfx-msg__meta--failed' => $status === 'failed'])>
                    {{ $message->created_at?->timezone(config('app.timezone'))->format('g:i a') }}
                    @if ($isOut && $status)
                        @php($statusKey = 'admin.resources.conversations.chat.statuses.'.$status)
                        · {{ \Illuminate\Support\Facades\Lang::has($statusKey) ? __($statusKey) : $status }}
                    @endif
                </span>
            </div>
        @empty
            <p class="rfx-chat__empty">{{ __('admin.resources.conversations.chat.empty') }}</p>
        @endforelse
    </div>

    @if (! $canReply)
        <p class="rfx-chat__notice">{{ __('admin.resources.conversations.chat.read_only') }}</p>
    @elseif (! $windowOpen)
        <p class="rfx-chat__notice">{{ __('admin.resources.conversations.chat.window_closed') }}</p>
    @else
        <form class="rfx-chat__composer" wire:submit="send">
            <textarea
                wire:model="draft"
                rows="2"
                maxlength="4000"
                placeholder="{{ __('admin.resources.conversations.chat.placeholder') }}"
                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $el.form.requestSubmit(); }"
            ></textarea>
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="send" icon="heroicon-m-paper-airplane">
                {{ __('admin.resources.conversations.chat.send') }}
            </x-filament::button>
        </form>
    @endif
</div>
