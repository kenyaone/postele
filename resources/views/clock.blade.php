<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Clock in / out &middot; Tele Caf&eacute;</title>
<style>
 *{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f7efe4;color:#2b1b14}
 .wrap{max-width:900px;margin:0 auto;padding:16px} h1{margin:0 0 4px;font-size:24px} .sub{color:#6b3f26;margin:0 0 14px}
 .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px} @media(max-width:700px){.grid{grid-template-columns:1fr}}
 .card{background:#fff;border-radius:14px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,.08)}
 .names{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;max-height:430px;overflow:auto}
 .name{border:2px solid #e6d6c6;background:#fff;border-radius:10px;padding:12px 8px;font-size:17px;cursor:pointer;text-align:left}
 .name small{display:block;color:#8a6a55;font-size:12px;margin-top:2px} .name.sel{border-color:#2b1b14;background:#2b1b14;color:#fff} .name.sel small{color:#e8d5c4}
 video,img.snap{width:100%;border-radius:10px;background:#000;aspect-ratio:4/3;object-fit:cover}
 input.pin{width:100%;font-size:26px;letter-spacing:8px;text-align:center;padding:10px;border:2px solid #e6d6c6;border-radius:10px;margin:10px 0}
 .btns{display:grid;grid-template-columns:1fr 1fr;gap:8px} button.go{font-size:20px;padding:16px;border:0;border-radius:10px;color:#fff;cursor:pointer}
 .in{background:#047857}.out{background:#9a3412} button:disabled{opacity:.4}
 .ok{background:#ecfdf5;border:1px solid #a7f3d0;padding:12px;border-radius:10px;margin-bottom:12px;font-size:18px}
 .err{background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:10px;margin-bottom:12px}
 .note{font-size:12px;color:#8a6a55;margin-top:10px} a{color:#6b3f26}
</style>
</head>
<body><div class="wrap">
 <h1>Clock in / out</h1>
 <p class="sub">{{ now()->format('l j F, H:i') }} &middot; tap your name, enter your PIN, look at the camera.</p>
 @if (session('ok'))<div class="ok">&#10003; {{ session('ok') }}</div>@endif
 @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
 <form method="POST" action="{{ route('clock.store') }}" id="f" class="grid">
  @csrf
  <input type="hidden" name="staff_id" id="sid" value="{{ old('staff_id') }}">
  <input type="hidden" name="photo" id="photo"><input type="hidden" name="kind" id="kind">
  <div class="card"><div class="names">
   @foreach ($staff as $s)
    @php $l = $last[$s->id] ?? null; @endphp
    <button type="button" class="name {{ old('staff_id') == $s->id ? 'sel' : '' }}" data-id="{{ $s->id }}">{{ $s->name }}<small>{{ $s->position }}@if ($l) &middot; {{ $l->kind === 'in' ? 'in' : 'out' }} {{ $l->at->format('H:i') }}@endif</small></button>
   @endforeach
  </div></div>
  <div class="card">
   <video id="v" autoplay playsinline muted></video><canvas id="c" width="480" height="360" hidden></canvas>
   <input class="pin" type="password" name="pin" id="pin" inputmode="numeric" autocomplete="off" maxlength="12" placeholder="PIN">
   <div class="btns"><button type="button" class="go in" data-k="in" disabled>Clock IN</button><button type="button" class="go out" data-k="out" disabled>Clock OUT</button></div>
   <p class="note" id="msg">A photo is taken when you clock in or out. Photos are kept for 60 days and only managers can see them.</p>
  </div>
 </form>
 <p class="note"><a href="{{ route('login') }}">&larr; Back to POS login</a></p>
</div>
<script>
(function () {
  var sid = document.getElementById('sid'), pin = document.getElementById('pin'), v = document.getElementById('v'),
      c = document.getElementById('c'), msg = document.getElementById('msg'), cam = false,
      btns = document.querySelectorAll('button.go');
  function ready() { btns.forEach(function (b) { b.disabled = !(cam && sid.value && pin.value.length >= 4); }); }
  document.querySelectorAll('button.name').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('button.name').forEach(function (x) { x.classList.remove('sel'); });
      b.classList.add('sel'); sid.value = b.dataset.id; pin.value = ''; pin.focus(); ready();
    });
  });
  pin.addEventListener('input', ready);
  if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
    navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: 'user' }, audio: false })
      .then(function (s) { v.srcObject = s; cam = true; ready(); })
      .catch(function () { msg.textContent = 'Camera is blocked. Allow the camera for this page in the browser, then reload.'; msg.style.color = '#b91c1c'; });
  } else { msg.textContent = 'This browser cannot use the camera.'; msg.style.color = '#b91c1c'; }
  btns.forEach(function (b) {
    b.addEventListener('click', function () {
      if (!v.videoWidth) { msg.textContent = 'Camera not ready yet, try again in a second.'; return; }
      c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
      document.getElementById('photo').value = c.toDataURL('image/jpeg', 0.7);
      document.getElementById('kind').value = b.dataset.k;
      btns.forEach(function (x) { x.disabled = true; });
      document.getElementById('f').submit();
    });
  });
  setTimeout(function () { location.reload(); }, 10 * 60 * 1000); // keep the time and list fresh
})();
</script>
</body></html>
