<script>
        // AJAX to delete log entry
        jQuery(document).ready(function ($) {
            $('.delete-log').on('click', function () {
                var logId = $(this).data('id');
                var data = {
                    'action': 'delete_twilio_sms_log',
                    'id': logId
                };

                $.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: data,
                    success: function (response) {
                        var result = JSON.parse(response);
                        if (result.status === 200) {
                            alert('Log entry deleted successfully');
                            location.reload();
                        } else {
                            alert('Error deleting log entry');
                        }
                    }
                });
            });
        });
    </script>