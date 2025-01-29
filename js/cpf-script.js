jQuery(document).ready(function($){

    $('#custom-profile-form').on('submit', function(e){
        e.preventDefault();

        var formData = $(this).serialize();
        $('#cpf-result').html('Submitting...');

        $.ajax({
            url: cpf_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'cpf_submit_form',
                nonce: cpf_ajax_obj.nonce,
                // Serialize the entire form data (includes all fields)
                // or pass individually
                ...Object.fromEntries(new URLSearchParams(formData))
            },
            success: function(response) {
                if (response.success) {
                    $('#cpf-result').css('color','green').html(response.data.message);
                    $('#custom-profile-form')[0].reset();
                } else {
                    $('#cpf-result').css('color','red').html(response.data.message);
                }
            },
            error: function(err) {
                $('#cpf-result').css('color','red').html('Error submitting form.');
            }
        });
    });

});
