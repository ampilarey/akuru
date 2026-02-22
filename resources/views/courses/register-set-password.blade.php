@extends('public.layouts.public')
@section('title', 'Complete Your Registration')
@section('content')
<section style="background:#F8F5F2;min-height:80vh;padding:3rem 1rem">
  <div style="max-width:36rem;margin:0 auto">

    {{-- Progress --}}
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:2rem">
      @foreach(['Verify Mobile','Complete Profile','Enroll'] as $i => $step)
      <div style="display:flex;align-items:center;gap:.5rem;flex:1">
        <div style="width:1.75rem;height:1.75rem;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:700;flex-shrink:0;
          {{ $i < 1 ? 'background:#7C2D37;color:white' : ($i===1 ? 'background:#C9A227;color:#3D1219' : 'background:#E5E7EB;color:#9CA3AF') }}">
          {{ $i < 1 ? '✓' : ($i+1) }}
        </div>
        <span style="font-size:.75rem;font-weight:{{ $i===1 ? '700' : '400' }};color:{{ $i===1 ? '#111827' : '#9CA3AF' }}">{{ $step }}</span>
        @if(!$loop->last)<div style="flex:1;height:1px;background:#E5E7EB"></div>@endif
      </div>
      @endforeach
    </div>

    <div style="background:white;border-radius:1rem;box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden">
      {{-- Header --}}
      <div style="padding:1.5rem 1.75rem;border-bottom:3px solid #C9A227">
        <h1 style="font-size:1.375rem;font-weight:800;color:#111827;margin:0 0 .25rem">Complete Your Profile</h1>
        <p style="font-size:.85rem;color:#6B7280;margin:0">Fill in your details to create your account.</p>
      </div>

      <div style="padding:1.75rem">
        @if($errors->any())
        <div style="margin-bottom:1.25rem;padding:.875rem;background:#FEF2F2;border:1px solid #FECACA;border-radius:.5rem;font-size:.85rem;color:#991B1B">
          <ul style="margin:0;padding-left:1.25rem">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
          </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('courses.register.set-password.store') }}">
          @csrf

          {{-- Name --}}
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;margin-bottom:1rem">
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">First Name <span style="color:#DC2626">*</span></label>
              <input type="text" name="first_name" value="{{ old('first_name') }}" required
                     style="width:100%;padding:.625rem .75rem;border:1.5px solid {{ $errors->has('first_name') ? '#FCA5A5' : '#E5E7EB' }};border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='{{ $errors->has('first_name') ? '#FCA5A5' : '#E5E7EB' }}'">
            </div>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">Last Name <span style="color:#DC2626">*</span></label>
              <input type="text" name="last_name" value="{{ old('last_name') }}" required
                     style="width:100%;padding:.625rem .75rem;border:1.5px solid {{ $errors->has('last_name') ? '#FCA5A5' : '#E5E7EB' }};border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
            </div>
          </div>

          {{-- Gender + DOB --}}
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.875rem;margin-bottom:1rem">
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">Gender <span style="color:#DC2626">*</span></label>
              <select name="gender" required style="width:100%;padding:.625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;background:white;box-sizing:border-box"
                      onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
                <option value="">Select</option>
                <option value="male" {{ old('gender')==='male' ? 'selected' : '' }}>Male</option>
                <option value="female" {{ old('gender')==='female' ? 'selected' : '' }}>Female</option>
              </select>
              @error('gender')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
            </div>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">Date of Birth <span style="color:#DC2626">*</span></label>
              <input type="date" name="dob" value="{{ old('dob') }}" required max="{{ date('Y-m-d') }}"
                     style="width:100%;padding:.625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
              @error('dob')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
            </div>
          </div>

          {{-- ID Type --}}
          <div style="margin-bottom:1rem">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.5rem">ID Document <span style="color:#DC2626">*</span></label>
            <div style="display:flex;gap:.75rem;margin-bottom:.625rem">
              <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;flex:1;padding:.625rem .875rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.875rem" id="lbl-nid">
                <input type="radio" name="id_type" value="national_id" {{ old('id_type','national_id')==='national_id' ? 'checked' : '' }} onchange="toggleIdFields(this.value)" style="accent-color:#7C2D37">
                <span>Maldivian ID Card</span>
              </label>
              <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;flex:1;padding:.625rem .875rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.875rem" id="lbl-pp">
                <input type="radio" name="id_type" value="passport" {{ old('id_type')==='passport' ? 'checked' : '' }} onchange="toggleIdFields(this.value)" style="accent-color:#7C2D37">
                <span>Passport</span>
              </label>
            </div>
            <div id="field-nid" style="{{ old('id_type')==='passport' ? 'display:none' : '' }}">
              <input type="text" name="national_id" value="{{ old('national_id') }}" placeholder="e.g. A123456"
                     style="width:100%;padding:.625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box;text-transform:uppercase"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
              @error('national_id')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
            </div>
            <div id="field-pp" style="{{ old('id_type')!=='passport' ? 'display:none' : '' }}">
              <input type="text" name="passport" value="{{ old('passport') }}" placeholder="Passport number"
                     style="width:100%;padding:.625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box;text-transform:uppercase"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
              @error('passport')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
            </div>
          </div>

          {{-- Email (optional) --}}
          <div style="margin-bottom:1rem">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">
              Email Address <span style="font-weight:400;color:#9CA3AF">(optional — for notifications)</span>
            </label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="email@example.com"
                   style="width:100%;padding:.625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box"
                   onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
            @error('email')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
          </div>

          {{-- Password --}}
          <div style="margin-bottom:1rem;padding-top:.875rem;border-top:1px solid #F3F4F6">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">Password <span style="color:#DC2626">*</span></label>
            <div style="position:relative">
              <input id="pw" type="password" name="password" required placeholder="Min 8 characters"
                     style="width:100%;padding:.625rem 2.75rem .625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
              <button type="button" onclick="togglePw('pw')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
            @error('password')<p style="font-size:.75rem;color:#DC2626;margin:.25rem 0 0">{{ $message }}</p>@enderror
          </div>
          <div style="margin-bottom:1.5rem">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:.375rem">Confirm Password <span style="color:#DC2626">*</span></label>
            <div style="position:relative">
              <input id="pw2" type="password" name="password_confirmation" required placeholder="Repeat password"
                     style="width:100%;padding:.625rem 2.75rem .625rem .75rem;border:1.5px solid #E5E7EB;border-radius:.5rem;font-size:.9rem;outline:none;box-sizing:border-box"
                     onfocus="this.style.borderColor='#7C2D37'" onblur="this.style.borderColor='#E5E7EB'">
              <button type="button" onclick="togglePw('pw2')" style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0">
                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>

          <button type="submit"
                  style="width:100%;padding:.875rem;border-radius:.625rem;font-weight:700;font-size:1rem;cursor:pointer;border:none;background:linear-gradient(135deg,#7C2D37,#5A1F28);color:white;transition:opacity .2s"
                  onmouseover="this.style.opacity='.9'" onmouseout="this.style.opacity='1'">
            Create Account &amp; Continue
          </button>
        </form>
      </div>
    </div>
  </div>
</section>

<script>
function togglePw(id) {
  const f = document.getElementById(id);
  f.type = f.type === 'password' ? 'text' : 'password';
}
function toggleIdFields(val) {
  document.getElementById('field-nid').style.display = val === 'national_id' ? '' : 'none';
  document.getElementById('field-pp').style.display  = val === 'passport'    ? '' : 'none';
}
</script>
@endsection
