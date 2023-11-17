$(document).ready(function () {
    if ($('.delivery-options-list .alert')) {
        $.ajax({
            url: '/module/whistleblower/action',
            type: 'POST',
            data: {
                action: 'alertJsCarrier',
            },

            success: function(response) {
                console.log("Mail send", response)
            },
            error: function(xhr, status, error) {
                console.error('Mail not send - Error : ', status, error);
            }
        });
    }
});