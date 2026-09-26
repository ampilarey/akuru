<nav x-data="{ open: false, adminOpen: false, userMenuOpen: false }"
     style="background:linear-gradient(135deg,#3D1219 0%,#7C2D37 100%);box-shadow:0 2px 12px rgba(0,0,0,.25);position:sticky;top:0;z-index:50;overflow:visible">
    {{-- Keyboard users land on the content, not on forty links (admin-panel layout audit, STATUS §5ht). --}}
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-2 focus:top-2 focus:z-[300] focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-sm focus:text-brandMaroon-800">Skip to content</a>

    <div style="max-width:80rem;margin:0 auto;padding:0 1.25rem">
        <div style="display:flex;justify-content:space-between;align-items:center;height:3.75rem">

            {{-- ── Logo ─────────────────────────────────────────────────────── --}}
            <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:.625rem;text-decoration:none;flex-shrink:0">
                <x-akuru-logo size="h-8" variant="on-dark" />
                <span style="color:white;font-weight:700;font-size:.95rem;letter-spacing:.01em">Akuru Institute</span>
            </a>

            {{-- ── Desktop nav links ────────────────────────────────────────── --}}
            <div class="hidden sm:flex" style="align-items:center;gap:.125rem;flex:1;justify-content:center">

                {{-- Dashboard --}}
                @php
                    $homeHref = auth()->user()?->isTeacher()
                        ? route('academics.registers.today')
                        : route('dashboard');
                    $homeActive = auth()->user()?->isTeacher()
                        ? request()->routeIs('academics.registers.today')
                        : request()->routeIs('dashboard');
                @endphp
                <a href="{{ $homeHref }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ $homeActive ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ $homeActive ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    @if(auth()->user()?->isParent())
                        Parent dashboard
                    @elseif(auth()->user()?->isTeacher())
                        Today
                    @else
                        Dashboard
                    @endif
                </a>

                @auth
                {{-- Enrollments (admin+) --}}
                @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
                <a href="{{ route('admin.enrollments.index') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('admin.enrollments.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('admin.enrollments.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Enrollments
                </a>
                @endif

                {{-- Students --}}
                @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
                <a href="{{ route('people.students.index') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('people.students.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('people.students.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Students
                </a>
                @endif

                {{-- Teachers --}}
                @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
                <a href="{{ route('people.staff.index') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('people.staff.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('people.staff.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Teachers
                </a>
                @endif

                {{-- Hifz Progress --}}
                @can('view_hifz_programs')
                <a href="{{ route('hifz.hub') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('hifz.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('hifz.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Hifz
                </a>
                @endcan

                {{-- Quran Progress --}}
                @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','teacher']))
                <a href="{{ route('quran-progress.index') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('quran-progress.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('quran-progress.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Quran
                </a>
                @endif

                {{-- More dropdown (CMS + Instructors + Substitutions + Announcements) --}}
                @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor','teacher']))
                <div style="position:relative" @click.away="adminOpen=false">
                    <button @click="adminOpen=!adminOpen" type="button"
                            aria-expanded="false" :aria-expanded="adminOpen" aria-controls="nav-more-menu"
                            style="display:flex;align-items:center;gap:.3rem;padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;background:transparent;border:none;cursor:pointer;color:rgba(255,255,255,.8);transition:background .15s"
                            onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'">
                        More
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="adminOpen" x-transition x-cloak id="nav-more-menu"
                         style="position:absolute;top:calc(100% + .5rem);inset-inline-start:0;min-width:220px;background:white;border-radius:.625rem;box-shadow:0 8px 30px rgba(0,0,0,.15);border:1px solid #E5E7EB;padding:.375rem;z-index:200">
                        {{-- The admin panel in its four parts — Admissions, Website & content,
                             Shops & money, System — the same headings as the hub at /admin
                             and the Inertia shell's More menu (`NavigationMap::adminPanel()`).
                             Each link keeps its own gate; a heading shows only when a link
                             under it does. --}}
                        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor','bookshop_manager']))
                        <a href="{{ route('admin.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none;font-weight:600" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🛠️ Admin panel</a>
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        @endif
                        <span style="display:block;padding:.5rem .75rem .15rem;font-size:.65rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9CA3AF" data-nav-section="school">School</span>
                        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor','teacher']))
                        <a href="{{ route('announcements.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📢 Announcements</a>
                        <a href="{{ route('substitutions.requests.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🔄 Substitutions</a>
                        @endif
                        <a href="{{ route('e-learning.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">💻 E-Learning</a>
                        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
                        <span style="display:block;padding:.5rem .75rem .15rem;font-size:.65rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9CA3AF" data-nav-section="panel_admissions">Admissions</span>
                        <a href="{{ route('admin.enrollments.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📝 Enrolments</a>
                        <a href="{{ route('admin.instructors.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">👨‍🏫 Instructors</a>
                        @endif
                        @if(auth()->user()->hasAnyRole(['super_admin','admin']) || auth()->user()->canAny(['prayer.manage', 'pronunciation.manage']))
                        <span style="display:block;padding:.5rem .75rem .15rem;font-size:.65rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9CA3AF" data-nav-section="panel_website">Website &amp; content</span>
                        @endif
                        @if(auth()->user()->hasAnyRole(['super_admin','admin']))
                        <a href="{{ route('admin.pages.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🌐 Website CMS</a>
                        <a href="{{ route('admin.courses.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📚 Manage Courses</a>
                        @endif
                        @can('prayer.manage')
                        <a href="{{ route('admin.prayer-times.islands') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🕌 Prayer times</a>
                        @endcan
                        @can('pronunciation.manage')
                        <a href="{{ route('admin.pronunciation.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🎤 Pronunciation</a>
                        @endcan
                        {{-- Commerce, the Library office and the Bookstore had no inbound link
                             from anywhere until STATUS §5ab; the prayer-times pages only linked
                             to each other. --}}
                        @canany(['commerce.manage', 'library.manage', 'bookshop.manage'])
                        <span style="display:block;padding:.5rem .75rem .15rem;font-size:.65rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9CA3AF" data-nav-section="panel_money">Shops &amp; money</span>
                        @endcanany
                        @can('commerce.manage')
                        <a href="{{ route('admin.commerce.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🎁 Commerce</a>
                        @endcan
                        @can('library.manage')
                        <a href="{{ route('admin.library.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📖 Digital Library</a>
                        @endcan
                        @can('bookshop.manage')
                        <a href="{{ route('admin.bookshop.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🛍️ Akuru Bookstore</a>
                        @endcan
                        {{-- Operations tools are gated by the permissions the routes check,
                             not by role, so nav and access agree. --}}
                        @if(auth()->user()->hasRole('super_admin') || auth()->user()->canAny(['operations.manage', 'translations.manage']))
                        <span style="display:block;padding:.5rem .75rem .15rem;font-size:.65rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase;color:#9CA3AF" data-nav-section="panel_system">System</span>
                        @endif
                        @if(auth()->user()->hasRole('super_admin'))
                        <a href="{{ route('admin.users.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;font-weight:600" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">👥 Manage Users</a>
                        <a href="{{ route('admin.settings.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;font-weight:600" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">⚙️ Settings</a>
                        @endif
                        @can('operations.manage')
                        <a href="{{ route('admin.operations.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📋 Ops checklist</a>
                        <a href="{{ route('admin.operations.features') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">✅ Feature walkthrough</a>
                        @endcan
                        @can('translations.manage')
                        <a href="{{ route('admin.translations.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🌐 Translations</a>
                        @endcan
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
                @php $navUser = auth()->user(); @endphp

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
                        <a href="{{ route('profile.edit') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">👤 My Profile</a>
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        <form method="POST" action="{{ route('logout') }}" style="margin:0">
                            @csrf
                            <button type="submit" style="width:100%;display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;background:none;border:none;cursor:pointer;text-align:start" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">🔒 Log Out</button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>

            {{-- ── Hamburger (mobile) ───────────────────────────────────────── --}}
            <button @click="open=!open" class="sm:hidden" type="button"
                    aria-label="Menu" aria-expanded="false" :aria-expanded="open" aria-controls="nav-mobile-menu"
                    style="padding:.5rem;border-radius:.375rem;background:rgba(255,255,255,.12);border:none;cursor:pointer">
                <svg style="width:1.25rem;height:1.25rem;stroke:white" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden':open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{'hidden':!open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ── Mobile menu ──────────────────────────────────────────────────── --}}
    <div x-show="open" x-transition x-cloak class="sm:hidden" id="nav-mobile-menu" data-testid="mobile-menu" style="border-top:1px solid rgba(255,255,255,.1);padding:.75rem 1rem">
        @auth
        <div style="margin-bottom:.75rem;padding:.625rem;background:rgba(255,255,255,.1);border-radius:.5rem">
            <p style="color:white;font-size:.85rem;font-weight:600;margin:0">{{ Auth::user()?->name }}</p>
            <p style="color:rgba(255,255,255,.6);font-size:.72rem;margin:.15rem 0 0">{{ ucwords(str_replace('_',' ', auth()->user()->getRoleNames()->first() ?? '')) }}</p>
        </div>
        @endauth

        <a href="{{ route('dashboard') }}" style="display:block;padding:.625rem .75rem;color:white;font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Dashboard</a>
        @auth
        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor','bookshop_manager']))
        <a href="{{ route('admin.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] font-semibold text-white hover:bg-white/10">Admin panel</a>
        @endif
        @endauth

        @auth
        {{-- The whole admin panel on a phone, in the same four parts as the desktop
             More menu and the hub at /admin, with the same gates. Until the layout
             audit (STATUS §5ht) the mobile menu stopped at the CMS, and the
             reachability test could not tell, because it counted a link anywhere
             in this file. --}}
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="school">School</span>
        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
        <a href="{{ route('people.students.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Students</a>
        <a href="{{ route('people.staff.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Teachers</a>
        @endif
        <a href="{{ route('announcements.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Announcements</a>
        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="panel_admissions">Admissions</span>
        <a href="{{ route('admin.enrollments.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Enrollments</a>
        <a href="{{ route('admin.instructors.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Instructors</a>
        @endif
        @if(auth()->user()->hasAnyRole(['super_admin','admin']) || auth()->user()->canAny(['prayer.manage', 'pronunciation.manage']))
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="panel_website">Website &amp; content</span>
        @endif
        @if(auth()->user()->hasAnyRole(['super_admin','admin']))
        <a href="{{ route('admin.pages.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Website CMS</a>
        <a href="{{ route('admin.courses.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Manage Courses</a>
        @endif
        @can('prayer.manage')
        <a href="{{ route('admin.prayer-times.islands') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Prayer times</a>
        @endcan
        @can('pronunciation.manage')
        <a href="{{ route('admin.pronunciation.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Pronunciation</a>
        @endcan
        @canany(['commerce.manage', 'library.manage', 'bookshop.manage'])
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="panel_money">Shops &amp; money</span>
        @endcanany
        @can('commerce.manage')
        <a href="{{ route('admin.commerce.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Commerce</a>
        @endcan
        @can('library.manage')
        <a href="{{ route('admin.library.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Digital Library</a>
        @endcan
        @can('bookshop.manage')
        <a href="{{ route('admin.bookshop.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Akuru Bookstore</a>
        @endcan
        @if(auth()->user()->hasRole('super_admin') || auth()->user()->canAny(['operations.manage', 'translations.manage']))
        <span class="block px-3 pb-0.5 pt-3 text-[.65rem] font-semibold uppercase tracking-wider text-white/50" data-nav-section="panel_system">System</span>
        @endif
        @if(auth()->user()->hasRole('super_admin'))
        <a href="{{ route('admin.users.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] font-semibold text-red-200 hover:bg-white/10">Manage Users</a>
        <a href="{{ route('admin.settings.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] font-semibold text-red-200 hover:bg-white/10">Settings</a>
        @endif
        @can('operations.manage')
        <a href="{{ route('admin.operations.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Ops checklist</a>
        <a href="{{ route('admin.operations.features') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Feature walkthrough</a>
        @endcan
        @can('translations.manage')
        <a href="{{ route('admin.translations.index') }}" class="block rounded px-3 py-2.5 text-[.85rem] text-white/85 hover:bg-white/10">Translations</a>
        @endcan

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
