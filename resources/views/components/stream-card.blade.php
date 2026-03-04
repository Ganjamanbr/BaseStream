@props(['stream'])

<a href="/api/streams/{{ $stream['id'] }}/1080"
   target="_blank"
   class="glass rounded-xl p-4 border border-purple-500/10 card-hover block text-center group">
    <div class="w-full h-16 rounded-lg bg-gradient-to-br {{ $stream['color'] }}
                flex items-center justify-center mb-3 group-hover:scale-105 transition-transform">
        <span class="text-white font-bold text-sm">{{ strtoupper($stream['id'][0]) }}</span>
    </div>
    <p class="text-white text-sm font-medium">{{ $stream['name'] }}</p>
    <p class="text-gray-500 text-xs mt-0.5">1080p</p>
</a>
