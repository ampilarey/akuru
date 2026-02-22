<nav x-data="{ open: false, adminOpen: false, cmsOpen: false }"
     style="background:linear-gradient(135deg,#3D1219 0%,#7C2D37 100%);box-shadow:0 2px 12px rgba(0,0,0,.25);position:sticky;top:0;z-index:50">

    <div style="max-width:80rem;margin:0 auto;padding:0 1.25rem">
        <div style="display:flex;justify-content:space-between;align-items:center;height:3.75rem">

            {{-- ── Logo ─────────────────────────────────────────────────────── --}}
            <a href="{{ route('dashboard') }}" style="display:flex;align-items:center;gap:.625rem;text-decoration:none;flex-shrink:0">
                <x-akuru-logo size="h-8" class="brightness-0 invert" />
                <span style="color:white;font-weight:700;font-size:.95rem;letter-spacing:.01em">Akuru Institute</span>
            </a>

            {{-- ── Desktop nav links ────────────────────────────────────────── --}}
            <div class="hidden sm:flex" style="align-items:center;gap:.125rem;flex:1;justify-content:center">

                {{-- Dashboard --}}
                <a href="{{ route('dashboard') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('dashboard') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('dashboard') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Dashboard
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
                <a href="{{ route('students.index') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('students.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('students.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Students
                </a>
                @endif

                {{-- Teachers --}}
                @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
                <a href="{{ route('teachers.index') }}"
                   style="padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;text-decoration:none;transition:background .15s;{{ request()->routeIs('teachers.*') ? 'background:rgba(255,255,255,.18);color:white' : 'color:rgba(255,255,255,.8)' }}"
                   onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='{{ request()->routeIs('teachers.*') ? 'rgba(255,255,255,.18)' : 'transparent' }}'">
                    Teachers
                </a>
                @endif

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
                    <button @click="adminOpen=!adminOpen"
                            style="display:flex;align-items:center;gap:.3rem;padding:.4rem .75rem;border-radius:.375rem;font-size:.8rem;font-weight:500;background:transparent;border:none;cursor:pointer;color:rgba(255,255,255,.8);transition:background .15s"
                            onmouseover="this.style.background='rgba(255,255,255,.12)'" onmouseout="this.style.background='transparent'">
                        More
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="adminOpen" x-transition
                         style="position:absolute;top:calc(100% + .5rem);left:0;min-width:180px;background:white;border-radius:.625rem;box-shadow:0 8px 30px rgba(0,0,0,.15);border:1px solid #E5E7EB;padding:.375rem;z-index:100">
                        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor','teacher']))
                        <a href="{{ route('announcements.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📢 Announcements</a>
                        @endif
                        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor','teacher']))
                        <a href="{{ route('substitutions.requests.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🔄 Substitutions</a>
                        @endif
                        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
                        <a href="{{ route('admin.instructors.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">👨‍🏫 Instructors</a>
                        @endif
                        @if(auth()->user()->hasAnyRole(['super_admin','admin']))
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        <a href="{{ route('admin.pages.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">🌐 Website CMS</a>
                        <a href="{{ route('admin.courses.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">📚 Manage Courses</a>
                        @endif
                        @if(auth()->user()->hasRole('super_admin'))
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        <a href="{{ route('admin.users.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;font-weight:600" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">👥 Manage Users</a>
                        <a href="{{ route('admin.settings.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;font-weight:600" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">⚙️ Settings</a>
                        @endif
                        <a href="{{ route('e-learning.index') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">💻 E-Learning</a>
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
                {{-- Role badge --}}
                @php $role = auth()->user()->getRoleNames()->first(); @endphp
                @if($role)
                <span style="font-size:.65rem;font-weight:700;padding:.2rem .55rem;border-radius:9999px;background:rgba(255,255,255,.15);color:rgba(255,255,255,.85);letter-spacing:.05em;text-transform:uppercase">
                    {{ str_replace('_',' ',$role) }}
                </span>
                @endif

                {{-- User dropdown --}}
                <div style="position:relative" @click.away="cmsOpen=false">
                    <button @click="cmsOpen=!cmsOpen"
                            style="display:flex;align-items:center;gap:.5rem;padding:.375rem .75rem;border-radius:.5rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);cursor:pointer;transition:background .15s"
                            onmouseover="this.style.background='rgba(255,255,255,.2)'" onmouseout="this.style.background='rgba(255,255,255,.12)'">
                        <div style="width:1.75rem;height:1.75rem;border-radius:50%;background:rgba(255,255,255,.25);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span style="font-size:.75rem;font-weight:700;color:white">{{ strtoupper(substr(Auth::user()?->name ?? 'U', 0, 1)) }}</span>
                        </div>
                        <span style="font-size:.8rem;font-weight:500;color:white;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ Auth::user()?->name ?? 'User' }}</span>
                        <svg width="12" height="12" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="cmsOpen" x-transition
                         style="position:absolute;top:calc(100% + .5rem);right:0;min-width:180px;background:white;border-radius:.625rem;box-shadow:0 8px 30px rgba(0,0,0,.15);border:1px solid #E5E7EB;padding:.375rem;z-index:100">
                        <div style="padding:.5rem .75rem;border-bottom:1px solid #F3F4F6;margin-bottom:.25rem">
                            <p style="font-size:.75rem;font-weight:600;color:#111827;margin:0">{{ Auth::user()?->name }}</p>
                            <p style="font-size:.7rem;color:#6B7280;margin:.1rem 0 0">{{ ucwords(str_replace('_',' ', $role ?? '')) }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}" style="display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#374151;text-decoration:none" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">👤 My Profile</a>
                        <div style="height:1px;background:#F3F4F6;margin:.25rem 0"></div>
                        <form method="POST" action="{{ route('logout') }}" style="margin:0">
                            @csrf
                            <button type="submit" style="width:100%;display:block;padding:.5rem .75rem;border-radius:.375rem;font-size:.8rem;color:#991B1B;text-decoration:none;background:none;border:none;cursor:pointer;text-align:left" onmouseover="this.style.background='#FEF2F2'" onmouseout="this.style.background='transparent'">🔒 Log Out</button>
                        </form>
                    </div>
                </div>
                @endauth
            </div>

            {{-- ── Hamburger (mobile) ───────────────────────────────────────── --}}
            <button @click="open=!open" class="sm:hidden"
                    style="padding:.5rem;border-radius:.375rem;background:rgba(255,255,255,.12);border:none;cursor:pointer">
                <svg style="width:1.25rem;height:1.25rem;stroke:white" fill="none" viewBox="0 0 24 24">
                    <path :class="{'hidden':open}" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    <path :class="{'hidden':!open}" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- ── Mobile menu ──────────────────────────────────────────────────── --}}
    <div x-show="open" x-transition class="sm:hidden" style="border-top:1px solid rgba(255,255,255,.1);padding:.75rem 1rem">
        @auth
        <div style="margin-bottom:.75rem;padding:.625rem;background:rgba(255,255,255,.1);border-radius:.5rem">
            <p style="color:white;font-size:.85rem;font-weight:600;margin:0">{{ Auth::user()?->name }}</p>
            <p style="color:rgba(255,255,255,.6);font-size:.72rem;margin:.15rem 0 0">{{ ucwords(str_replace('_',' ', auth()->user()->getRoleNames()->first() ?? '')) }}</p>
        </div>
        @endauth

        <a href="{{ route('dashboard') }}" style="display:block;padding:.625rem .75rem;color:white;font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Dashboard</a>

        @auth
        @if(auth()->user()->hasAnyRole(['super_admin','admin','headmaster','supervisor']))
        <a href="{{ route('admin.enrollments.index') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Enrollments</a>
        <a href="{{ route('students.index') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Students</a>
        <a href="{{ route('teachers.index') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Teachers</a>
        @endif
        <a href="{{ route('announcements.index') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Announcements</a>
        @if(auth()->user()->hasAnyRole(['super_admin','admin']))
        <a href="{{ route('admin.pages.index') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem" onmouseover="this.style.background='rgba(255,255,255,.1)'" onmouseout="this.style.background='transparent'">Website CMS</a>
        @endif

        <div style="border-top:1px solid rgba(255,255,255,.1);margin:.75rem 0;padding-top:.75rem">
            <a href="{{ route('profile.edit') }}" style="display:block;padding:.625rem .75rem;color:rgba(255,255,255,.85);font-size:.85rem;text-decoration:none;border-radius:.375rem">My Profile</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" style="width:100%;padding:.625rem .75rem;color:#FCA5A5;font-size:.85rem;background:none;border:none;cursor:pointer;text-align:left;border-radius:.375rem">Log Out</button>
            </form>
        </div>
        @endauth
    </div>
</nav>
