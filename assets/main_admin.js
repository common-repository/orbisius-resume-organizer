// Orbisius Market > Admin JS
jQuery(document).ready(function($) {
    $('.orbisius_resume_organizer_admin_delete_attachment').click(function (e) {
        var parent_container = $(this).closest('tr');
        var ajax_url = $(this).attr('href');

        if (!confirm('Are you sure?', '')) {
            return false;
        }
        
        jQuery.ajax({
            type : "post",
            dataType : "json",
            url : ajax_url, // contains all the necessary params
            //data : { action: "my_user_vote", post_id : post_id, nonce: nonce },
            success: function(json) {
               if (json.status) {
                  jQuery(parent_container).slideUp('slow').remove();
               } else {
                  alert("There was an error.");
               }
            }
        });

        return false;
    });
});
