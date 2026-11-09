"use strict";

// Class Definition
var KTSigninTwoFactor = function() {
    // Elements
    var form;
    var submitButton;

    // Handle form
    var handleForm = function(e) {        
        // Handle form submit button click
        submitButton.addEventListener('click', function (e) {
            e.preventDefault();

            var validated = true;
            var inputs = [].slice.call(form.querySelectorAll('input[maxlength="1"]'));
            inputs.forEach(function (input) {
                if (input.value === '' || input.value.length === 0) {
                    validated = false;
                }
            });

            if (validated === true) {
                // Show loading indication
                submitButton.setAttribute('data-kt-indicator', 'on');

                // Disable button to avoid multiple click 
                submitButton.disabled = true;

                // Enviar el formulario para validación en el servidor
                form.submit();
            } else {
                Swal.fire({
                    text: "Por favor ingresa los 6 dígitos del código de verificación.",
                    icon: "warning",
                    buttonsStyling: false,
                    confirmButtonText: "Entendido",
                    customClass: {
                        confirmButton: "btn btn-primary"
                    }
                }).then(function() {
                    KTUtil.scrollTop();
                });
            }
        });

        // Handle Enter key on inputs
        form.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                submitButton.click();
            }
        });
    }

    var handleType = function() {
        var input1 = form.querySelector("[name=code_1]");
        var input2 = form.querySelector("[name=code_2]");
        var input3 = form.querySelector("[name=code_3]");
        var input4 = form.querySelector("[name=code_4]");
        var input5 = form.querySelector("[name=code_5]");
        var input6 = form.querySelector("[name=code_6]");
        var inputs = [input1, input2, input3, input4, input5, input6];

        if (input1) input1.focus();

        inputs.forEach(function(input, index) {
            if (!input) return;

            input.addEventListener("input", function() {
                if (this.value.length >= 1) {
                    this.value = this.value.charAt(0);
                    if (index < inputs.length - 1 && inputs[index + 1]) {
                        inputs[index + 1].focus();
                    } else if (index === inputs.length - 1) {
                        this.blur();
                    }
                }
            });

            input.addEventListener("keydown", function(e) {
                if (e.key === "Backspace" && this.value === "" && index > 0 && inputs[index - 1]) {
                    inputs[index - 1].focus();
                }
            });

            input.addEventListener("paste", function(e) {
                e.preventDefault();
                var clipboard = (e.clipboardData || window.clipboardData);
                if (!clipboard) return;
                var pastedData = clipboard.getData('text').trim();
                var digits = pastedData.replace(/\D/g, '').substring(0, 6);
                if (digits.length > 0) {
                    for (var i = 0; i < digits.length; i++) {
                        if (inputs[i]) inputs[i].value = digits[i];
                    }
                    if (digits.length === 6) {
                        inputs[5].focus();
                    } else if (digits.length < 6 && inputs[digits.length]) {
                        inputs[digits.length].focus();
                    }
                }
            });
        });
    }    

    // Public functions
    return {
        // Initialization
        init: function() {
            form = document.querySelector('#kt_sing_in_two_factor_form');
            submitButton = document.querySelector('#kt_sing_in_two_factor_submit');

            handleForm();
            handleType();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function() {
    KTSigninTwoFactor.init();
});