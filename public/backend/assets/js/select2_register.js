/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 *
 * Register your select2 elements here
 * this will make it easier for you tu find components
 */

"use strict";

$(document).ready(() => {
    //select2
    setTimeout(() => {
        $("#feature_category_item").select2({
            placeholder: select_category,
        });
        $('#partners_ids').select2({
            placeholder: select_provider,
            // dropdownParent: $("#update_modal")
        });

        $("#category_ids").select2({
            placeholder: select_category,
        });
        $("#partner_tags").select2({
            placeholder: select_tag,
        });
        // $("#category_item").select2({
        //     placeholder: "Select Category",
        // });
        $("#sub_category").select2({
            placeholder: select_sub_category,
        });
        $("#users").select2({
            placeholder: select_user,
        });
        $("#edit_partners_ids").select2({
            placeholder: select_provider,
        });

        $("#ticket-status").select2({
            placeholder: select_ticket_status,
        });

        // bug solving 
        $("#parent_id_edit").select2({
            placeholder: select_category,
        });

        $("#edit_Category_item").select2({
            // this is  is for edit featured section 
            placeholder: select_category,
        });

        // for service edit
        $("#edit_sub_category").select2({
            // this is  is for edit featured section 
            placeholder: select_sub_category,
        });

        // for Display parent
        $("#make_parent").select2({
            // this is  is for edit featured section 
            placeholder: select_category,
        });

        // commented  for some time will uncomment when needed

        // $("#user_name").select2({
        //     placeholder: "Select categories",
        // });
        // $("#role").select2({
        //     placeholder: "Select categories",
        // });


        $("#service_custom_provider_filter").select2({
            placeholder: select_provider,
            dropdownParent: $('#filterDrawer')
        });

        $("#service_category_custom_filter").select2({
            placeholder: select_category,
            dropdownParent: $('#filterDrawer')
        });

        $("#order_provider_filter").select2({
            placeholder: select_provider,
            dropdownParent: $('#filterDrawer')
        });

        $("#order_status_filter").select2({
            placeholder: "Select Status",
            dropdownParent: $('#filterDrawer')
        });

        $("#blog_category_custom_filter").select2({
            placeholder: select_category,
            dropdownParent: $("#filterDrawer")
        });

    }, 100);
});


// for media query


if (window.matchMedia('(max-width: 320px)').matches) {
    $('.invoice-partner-image').removeClass('w-25');

    // 
    $('.invoice-text').removeClass('text-sm-right');
    $('.invoice-text').addClass('text-center');


}

if (window.matchMedia('(max-width: 768px)').matches) {

    if ($('#input_group').hasClass('col-md-10')) {
        $('#input_group').removeClass('col-md-10');
        $('#input_group').removeClass('col-sm-10');
        $('#input_group').addClass('col-md-9, col-sm-9');
    }
}