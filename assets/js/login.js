var valid = true;

// Handle login form submission
$(document).on('submit', '#form-login', function(event) {
    event.preventDefault();

    // Initialize validation flag
    var isValid = true;

    // Collect form data
    var formData = {
        un: $('#un').val(),
        pwd: $('#pwd').val()
    };

    // Validate form fields
    $.each(formData, function(key, value) {
        if (!value) {
            $('#' + key).closest('.form-group').addClass('has-error');
            isValid = false;
        } else {
            $('#' + key).closest('.form-group').removeClass('has-error');
        }
    });

    // If form data is valid, send AJAX request
    if (isValid) {
        $.ajax({
            url: 'data/user_login.php',
            type: 'post',
            dataType: 'json',
            data: formData,
            success: function(data) {
                if (data.valid === valid) {
                    window.location.href = data.url;
                } else {
                    $('#form-login').find('.text-danger').text(data.msg || "Unknown error occurred.");
                    console.log("Error message:", data.msg);
                }
            },
            error: function(xhr, status, error) {
                alert('AJAX Error: ' + error); // Concatenate error message with string
                alert('Error: Unable to process your request. Please try again later.');
            }
        });
    }
});
