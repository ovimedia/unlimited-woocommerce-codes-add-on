jQuery(document).ready(function($) {

    jQuery('#zone-woocode select').select2({ tags: true });

    jQuery("#uwc_type").change(function() {

        if (jQuery(this).val() == "woocommerce")
            jQuery(".woooption").css("display", "block");
        else
            jQuery(".woooption").css("display", "none");
    });

    if (jQuery("#uwc_type").val() != "neither")
        jQuery(".woooption").css("display", "block");
});