document.addEventListener('DOMContentLoaded', () => {
  const container = document.querySelector('.container');
  const login = document.getElementById('login-form');
  const signup = document.getElementById('signup-form');
  if (new URLSearchParams(window.location.search).get('registro') === '1') container.classList.add('active');
  const message = document.createElement('p');
  message.setAttribute('role', 'status');
  container.before(message);
  let csrf = '';
  const ready = fetch(window.ClinicaESB.url('sesion.php'), {credentials: 'same-origin'})
    .then(r => r.json()).then(d => { if (!d.success) throw Error(d.message); csrf = d.csrfToken; if (d.usuario) window.location.replace('citas.html'); });
  ready.catch(() => { message.textContent = 'No se pudo conectar. Abra la pagina desde el servidor PHP.'; });
  document.querySelectorAll('.register-link,.login-link,.login-button').forEach(link => {
    link.addEventListener('click', e => { e.preventDefault(); container.classList.toggle('active', link.classList.contains('register-link')); });
  });
  signup.elements.password.minLength = 8;
  signup.elements.password.maxLength = 72;
  const confirm = document.getElementById('confirm-password');
  function validate() {
    const ok = signup.elements.password.value === confirm.value;
    document.getElementById('password-error').style.display = ok ? 'none' : 'block';
    confirm.setCustomValidity(ok ? '' : 'Las contrasenas no coinciden.');
  }
  confirm.addEventListener('input', validate);
  signup.elements.password.addEventListener('input', validate);
  async function submit(event, endpoint) {
    event.preventDefault();
    const form = event.currentTarget;
    if (form === signup) validate();
    if (!form.reportValidity()) return;
    const button = form.querySelector('button[type=submit]'); button.disabled = true;
    try {
      await ready;
      const r = await fetch(window.ClinicaESB.url(endpoint), {method: 'POST', credentials: 'same-origin', headers: {'X-CSRF-Token': csrf, 'Content-Type': 'application/json'}, body: JSON.stringify(Object.fromEntries(new FormData(form)))});
      const d = await r.json();
      message.textContent = d.message || 'Operacion completada.';
      if (r.ok && d.success) {
        if (d.redirect) window.location.assign(d.redirect);
        else { form.reset(); container.classList.remove('active'); }
      }
    } catch (_) { message.textContent = 'No se pudo completar la solicitud. Revise la conexion al servidor.'; }
    finally { button.disabled = false; }
  }
  login.addEventListener('submit', e => submit(e, 'php/procesar_login.php'));
  signup.addEventListener('submit', e => submit(e, 'php/procesar_registro.php'));
});
