<span x-cloak x-show="chatUnreadCount > 0" x-text="chatUnreadLabel" aria-live="polite"
      :aria-label="@js(__(':count unread direct conversations')).replace(':count', chatUnreadCount)"
      {{ $attributes->class(['rounded-full bg-fuchsia-600 px-1.5 py-0.5 text-[10px] font-bold tabular-nums text-white']) }}></span>
