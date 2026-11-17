document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.container');
    const registerLink = document.querySelector('.register-link');
    const loginLink = document.querySelector('.login-link');
    const loginButton = document.querySelector('.login-button');
    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const passwordError = document.getElementById('password-error');

    registerLink.addEventListener('click', (e) => {
        e.preventDefault();
        container.classList.add('active');
    });

    loginLink.addEventListener('click', (e) => {
        e.preventDefault();
        container.classList.remove('active');
    });

    loginButton.addEventListener('click', (e) => {
        e.preventDefault();
        container.classList.remove('active');
        container.scrollIntoView({ behavior: 'smooth' });
    });

    function validatePasswords() {
        if (passwordInput.value !== confirmPasswordInput.value) {
            passwordError.style.display = 'block';
            return false;
        } else {
            passwordError.style.display = 'none';
            return true;
        }
    }

    passwordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);

    function handleFormSubmit(url, form) {
        const formData = new FormData(form);

        fetch(url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                if (data.redirect) {
                    window.location.href = data.redirect;
                }
                if (form === signupForm) {
                    form.reset();
                    container.classList.remove('active');
                }
            } else {
                alert(data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }

    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        handleFormSubmit('php/procesar_login.php', this);
    });

    signupForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (validatePasswords()) {
            handleFormSubmit('php/procesar_registro.php', this);
        } else {
            alert('Las contraseñas no coinciden. Por favor, inténtalo de nuevo.');
        }
    });

    // Modificación para redirigir al usuario a la página principal después de iniciar sesión
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Aquí iría tu lógica de autenticación
        // Por ejemplo, una llamada a una API o verificación de credenciales

        // Suponiendo que la autenticación fue exitosa:
        if (autenticacionExitosa) {
            // Redirigir a health-center-master/index.html
            window.location.href = '/health-center-master/index.html';
        } else {
            // Mostrar un mensaje de error si la autenticación falla
            alert('Credenciales incorrectas. Por favor, intente de nuevo.');
        }
    });
});