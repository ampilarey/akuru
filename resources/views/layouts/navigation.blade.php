@php
    // The Blade shell reads the same map as the Inertia shell (STATUS §5id):
    // the person's active workspace decides the bar and the More menu, each
    // link is one the routes admit, and the switcher offers their other
    // workspaces. Until then this file was 66 inline links gated by hand,
    // drifting from the Inertia shell (docs/ADMIN_PANEL.md L7–L10).
    $navUser = auth()->user();
    $workspaces = $navUser ? app(\App\Support\Navigation\ResolveWorkspacesAction::class)->execute($navUser) : ['active' => null, 'list' => []];
    $nav = $navUser ? app(\App\Support\Navigation\BuildNavigationAction::class)->execute($navUser, app()->getLocale(), $workspaces['active']) : ['primary' => [], 'groups' => []];
    $activeWorkspace = collect($workspaces['list'])->firstWhere('key', $workspaces['active']);
    $currentPath = '/'.ltrim(preg_replace('#^(en|dv|ar)(?=/|$)#', '', request()->path()) ?? '', '/');
    $isCurrent = fn (string $href): bool => $currentPath === $href || str_starts_with($currentPath, $href.'/');
    $localize = fn (string $href): string => \Mcamara\LaravelLocalization\Facades\LaravelLocalization::localizeURL($href);
    $barLink = 'padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;';
    $menuItem = 'display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none';
    $menuHead = 'display:block;padding:.5rem .75rem .15rem;font-size:.65rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9CA3AF';
    $menuForm = 'width:100%;display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;background:none;border:none;cursor:pointer;text-align:start';
@endphp
<nav x-data="{ open: false, adminOpen: false, userMenuOpen: false, workspaceOpen: false }"
     style="background:linear-gradient(135deg,#3D1219 0%,#7C2D37 100%);box-shadow:0 2px 12px rgba(0,0,0,.25);position:sticky;top:0;z-index:50;overflow:visible">
    {{-- Keyboard users land on the content, not on forty links (admin-panel layout audit, STATUS §5ht). --}}
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-[300] focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:text-brandMaroon-800">Skip to content</a>

    <div style="max-width:80rem;margin:0 auto;padding:0 1.25rem">
        <div style="display:flex;justify-content:space-between;align-items:center;height:3.75rem;gap:.75rem">

            {{-- ── Logo, and the workspace switcher ────────────────────────── --}}
            <div style="display:flex;align-items:center;gap:.75rem;flex-shrink:0">
                <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:.625rem;text-decoration:none;flex-shrink:0">
                    <x-akuru-logo size="h-8" variant="on-dark" />
                    <span style="color:white;font-weight:700;font-size:.95rem;letter-spacing:.01em">Akuru Institute</span>
                </a>
                @auth
                @if(count($workspaces['list']) > 1)
                <div style="position:relative" class="hidden sm:block" @click.away="workspaceOpen=false">
                    <button @click="workspaceOpen=!workspaceOpen" type="button"
                            aria-expanded="false" :aria-expanded="workspaceOpen" aria-controls="nav-workspaces" data-testid="workspace-switcher"
                            style="display:flex;align-items:center;gap:.3rem;padding:.3rem .75rem;border-radius:9999px;font-size:.8rem;font-weight:500;background:transparent;border:1px solid rgba(255,255,255,.4);cursor:pointer;color:white">
                        {{ $activeWorkspace['label'] ?? '' }}
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="workspaceOpen" x-transition x-cloak id="nav-workspaces"
                         style="position:absolute;top:calc(100% + .5rem);inset-inline-start:0;min-width:200px;background:white;border-radius:.625rem;box-shadow:0 8px 30px rgba(0,0,0,.15);border:1px solid #E5E7EB;padding:.375rem;z-index:200">
                        <span style="{{ $menuHead }}">{{ __('nav.workspaces') }}</span>
                        @foreach($workspaces['list'] as $workspace)
                        <form method="POST" action="{{ route('workspace.switch', $workspace['key']) }}" style="margin:0">
                            @csrf
                            <button type="submit" data-testid="workspace-{{ $workspace['key'] }}" style="{{ $menuForm }}{{ $workspace['key'] === $workspaces['active'] ? ';font-weight:700;color:#7C2D37' : '' }}" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">{{ $workspace['label'] }}</button>
                        </form>
                        @endforeach
                    </div>
                </div>
                @endif
                @endauth
            </div>

            {{-- ── Desktop nav links: the workspace's bar, then More ─────────── --}}
            <div class="hidden sm:flex" style="align-items:center;gap:.125rem;flex:1;justify-content:center;min-width:0">
                <a href="{{ route('dashboard') }}"
                   style="{{ $barLink }}{{ request()->routeIs('dashboard') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('dashboard') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Dashboard
                </a>
                @foreach($nav['primary'] as $item)
                <a href="{{ $localize($item['href']) }}"
                   style="{{ $barLink }}{{ $isCurrent($item['href']) ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ $isCurrent($item['href']) ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    {{ $item['label'] }}
                </a>
                @endforeach

                @auth
                @if($nav['groups'] !== [])
                <div style="position:relative" @click.away="adminOpen=false">
                    <button @click="adminOpen=!adminOpen" type="button"
                            aria-expanded="false" :aria-expanded="adminOpen" aria-controls="nav-more-menu"
                            style="display:flex;align-items:center;gap:.3rem;padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;background:transparent;border:none;cursor:pointer;color:rgba(255,255,255,.8);transition:background .15s"
                            onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'">
                        More
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="adminOpen" x-transition x-cloak id="nav-more-menu"
                         style="position:absolute;top:calc(100% + .5rem);inset-inline-start:0;min-width:220px;max-height:80vh;overflow-y:auto;background:white;border-radius:.625rem;box-shadow:0 8px 30px rgba(0,0,0,.15);border:1px solid #E5E7EB;padding:.375rem;z-index:200">
                        @if($activeWorkspace)
                        <a href="{{ $activeWorkspace['href'] }}" data-testid="workspace-home" style="{{ $menuItem }};font-weight:600" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🏠 {{ $activeWorkspace['label'] }}</a>
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        @endif
                        @foreach($nav['groups'] as $group)
                        <span style="{{ $menuHead }}" data-nav-section="{{ $group['key'] }}">{{ $group['label'] }}</span>
                        @foreach($group['items'] as $item)
                        <a href="{{ $localize($item['href']) }}" style="{{ $menuItem }}{{ $isCurrent($item['href']) ? ';font-weight:600;color:#7C2D37' : '' }}" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">{{ $item['label'] }}</a>
                        @endforeach
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- View Website link --}}
                <a href="{{ route('public.home') }}" target="_blank"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;color:rgba(255,255,255,.6);transition:background .15s"
                   onmouseover="this.style.color='white'" onmouseout="this.style.color='rgba(255,255,255,.6)'">
                    ↗ Website
                </a>
                @endauth
            </div>

            {{-- ── Right: user dropdown ─────────────────────────────────────── --}}
            <div class="hidden sm:flex" style="align-items:center;gap:.75rem">

                @auth
                {{-- User dropdown (role badge removed — shown once inside menu) --}}
                <div style="position:relative" @click.away="userMenuOpen=false">
                    <button type="button" @click="userMenuOpen=!userMenuOpen"
                            aria-expanded="false" :aria-expanded="userMenuOpen"
                            style="display:flex;align-items:center;gap:.5rem;padding:.375rem .75rem;border-radius:.5rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);cursor:pointer;transition:background .15s"
                            onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
                        <div style="width:1.75rem;height:1.75rem;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span style="font-size:.75rem;font-weight:700;color:white">{{ strtoupper(substr($navUser->navLabel(), 0, 1)) }}</span>
                        </div>
                        <span style="font-size:.8rem;font-weight:500;color:white;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $navUser->navLabel() }}</span>
                        <svg width="12" height="12" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="userMenuOpen" x-transition x-cloak
                         style="position:absolute;top:calc(100% + .5rem);inset-inline-end:0;min-width:200px;background:white;border-radius:.625rem;box-shadow:0 8px 30px rgba(0,0,0,.15);border:1px solid #E5E7EB;padding:.375rem;z-index:200">
                        <div style="padding:.5rem .75rem;border-bottom:1px solid #F3F4F6;margin-bottom:.25rem">
                            @if($navUser->nameDuplicatesPrimaryRole())
                            <p style="font-size:.75rem;font-weight:600;color:#111827;margin:0">{{ $navUser->email ?: $navUser->phone }}</p>
                            @else
                            <p style="font-size:.75rem;font-weight:600;color:#111827;margin:0">{{ $navUser->name }}</p>
                            @endif
                            @if($navUser->primaryRoleLabel())
                            <p style="font-size:.7rem;color:#6B7280;margin:.1rem 0 0">{{ $navUser->primaryRoleLabel() }}</p>
                            @endif
                        </div>
                        <a href="{{ route('profile.edit') }}" style="{{ $menuItem }}" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">👤 My Profile</a>
                        @if(count($workspaces['list']) > 1)
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        <span style="{{ $menuHead }}">{{ __('nav.workspaces') }}</span>
                        @foreach($workspaces['list'] as $workspace)
                        <form method="POST" action="{{ route('workspace.switch', $workspace['key']) }}" style="margin:0">
                            @csrf
                            <button type="submit" data-testid="workspace-{{ $workspace['key'] }}" style="{{ $menuForm }}{{ $workspace['key'] === $workspaces['active'] ? ';font-weight:700;color:#7C2D37' : '' }}" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">{{ $workspace['label'] }}</button>
                        </form>
                        @endforeach
                        @endif
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        <form method="POST" action="{{ route('logout') }}" style="margin:0">
                            @csrf
                            <button type="submit" style="width:100%;display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;background:none;border:none;cursor:pointer;text-align:start" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">🔒 Log Out</button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>

            {{-- On a phone the initial opens this menu — the same list More opens
                 on a desk (the owner, 2026-10-02). A visitor has no initial, so
                 they keep the menu button. --}}
            @auth
            <button @click="open=!open" class="sm:hidden" type="button"
                    aria-label="Menu" aria-expanded="false" :aria-expanded="open" aria-controls="nav-mobile-menu"
                    data-testid="shell-avatar"
                    style="width:2.25rem;height:2.25rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;flex-shrink:0;border:none;cursor:pointer;color:white;font-weight:700;font-size:.875rem;background:rgba(255,255,255,.25)"
                    :style="{ background: open ? 'rgba(255,255,255,.4)' : 'rgba(255,255,255,.25)', boxShadow: open ? '0 0 0 2px white' : 'none' }">
                {{ strtoupper(substr($navUser->navLabel(), 0, 1)) }}
            </button>
            @else
            <button @click="open=!open" class="sm:hidden" type="button"
                    aria-label="Menu" aria-expanded="false" :aria-expanded="open" aria-controls="nav-mobile-menu"
                    style="padding:.5rem;border-radius:.375rem;background:rgba(255,255,255,.12);border:none;cursor:pointer">
                <svg style="width:1.25rem;height:1.25rem;stroke:white" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden':open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{'hidden':!open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            @endauth
        </div>
    </div>

    {{-- ── Mobile menu: the same workspace, the same map ────────────────── --}}
    <div x-show="open" x-transition x-cloak class="sm:hidden" id="nav-mobile-menu" data-testid="mobile-menu" style="border-top:1px solid rgba(255,255,255,.1);padding:.75rem 1rem">
        @auth
        <div style="margin-bottom:.75rem;padding:.625rem;background:rgba(255,255,255,.1);border-radius:.5rem">
            <p style="color:white;font-size:.85rem;font-weight:600;margin:0">{{ $navUser->name }}</p>
            <p style="color:rgba(255,255,255,.6);font-size:.72rem;margin:.15rem 0 0">{{ $navUser->primaryRoleLabel() ?? '' }}</p>
        </div>

        <a href="{{ route('dashboard') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white hover:bg-white/10">Dashboard</a>
        @if(count($workspaces['list']) > 1)
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="workspaces">{{ __('nav.workspaces') }}</span>
        @foreach($workspaces['list'] as $workspace)
        <form method="POST" action="{{ route('workspace.switch', $workspace['key']) }}" class="m-0">
            @csrf
            <button type="submit" data-testid="workspace-{{ $workspace['key'] }}" class="block w-full rounded px-3 py-2.5 text-start text-[.85rem] {{ $workspace['key'] === $workspaces['active'] ? 'font-semibold text-white' : 'text-white/85' }} hover:bg-white/10">{{ $workspace['label'] }}</button>
        </form>
        @endforeach
        @endif
        @if($activeWorkspace)
        <a href="{{ $activeWorkspace['href'] }}" data-testid="workspace-home" class="block rounded px-3 py-2.5 text-[.85rem] font-semibold text-white hover:bg-white/10">🏠 {{ $activeWorkspace['label'] }}</a>
        @endif
        @foreach($nav['primary'] as $item)
        <a href="{{ $localize($item['href']) }}" class="block rounded px-3 py-2.5 text-[.85rem] {{ $isCurrent($item['href']) ? 'font-semibold text-white' : 'text-white/85' }} hover:bg-white/10">{{ $item['label'] }}</a>
        @endforeach
        @foreach($nav['groups'] as $group)
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="{{ $group['key'] }}">{{ $group['label'] }}</span>
        @foreach($group['items'] as $item)
        <a href="{{ $localize($item['href']) }}" class="block rounded px-3 py-2.5 text-[.85rem] {{ $isCurrent($item['href']) ? 'font-semibold text-white' : 'text-white/85' }} hover:bg-white/10">{{ $item['label'] }}</a>
        @endforeach
        @endforeach

        <div style="border-top:1px solid rgba(255,255,255,.1);margin:.75rem 0;padding-top:.75rem">
            <a href="{{ route('profile.edit') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem">My Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" style="width:100%;padding:.625rem .75rem;color:#FCA5A5;font-size:.85rem;background:none;border:none;cursor:pointer;text-align:start;border-radius:.375rem">Log Out</button>
            </form>
        </div>
        @endauth
    </div>
</nav>
