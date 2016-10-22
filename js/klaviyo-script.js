// JavaScript Document

jQuery(document).on("change keyup paste keydown","#klaviyo_api_key", function(e) {
	var val = jQuery(this).val();
	if( val !== "" )
		jQuery("#auth-klaviyo").removeAttr('disabled');
	else
		jQuery("#auth-klaviyo").attr('disabled','true');
});

jQuery(document).on( "click", "#auth-klaviyo", function(e){
	e.preventDefault();
	jQuery(".smile-absolute-loader").css('visibility','visible');
	var klaviyo_api_key = jQuery("#klaviyo_api_key").val();

	var action = 'update_klaviyo_authentication';
	var data = {action:action,klaviyo_api_key:klaviyo_api_key};
	jQuery.ajax({
		url: ajaxurl,
		data: data,
		type: 'POST',
		dataType: 'JSON',
		success: function(result){
			if(result.status == "success" ){
				jQuery(".bsf-cnlist-mailer-help").hide();
				jQuery("#save-btn").removeAttr('disabled');
				jQuery("#klaviyo_api_key").closest('.bsf-cnlist-form-row').hide();
				jQuery("#auth-klaviyo").closest('.bsf-cnlist-form-row').hide();
				jQuery(".klaviyo-list").html(result.message);

			} else {
				jQuery(".klaviyo-list").html('<span class="bsf-mailer-success">'+result.message+'</span>');
			}
			jQuery(".smile-absolute-loader").css('visibility','hidden');
		}
	});
	e.preventDefault();
});

jQuery(document).on( "click", "#disconnect-klaviyo", function(){

	if(confirm("Are you sure? If you disconnect, your previous campaigns syncing with Klaviyo will be disconnected as well.")) {
		var action = 'disconnect_klaviyo';
		var data = {action:action};
		jQuery(".smile-absolute-loader").css('visibility','visible');
		jQuery.ajax({
			url: ajaxurl,
			data: data,
			type: 'POST',
			dataType: 'JSON',
			success: function(result){

				jQuery("#save-btn").attr('disabled','true');
				if(result.message == "disconnected" ){
					jQuery("#klaviyo_api_key").val('');
					jQuery(".klaviyo-list").html('');
					jQuery("#disconnect-klaviyo").replaceWith('<button id="auth-klaviyo" class="button button-secondary auth-button" disabled="true">Authenticate Klaviyo</button><span class="spinner" style="float: none;"></span>');
					jQuery("#auth-klaviyo").attr('disabled','true');
				}

				jQuery('.bsf-cnlist-form-row').fadeIn('300');
				jQuery(".bsf-cnlist-mailer-help").show();
				jQuery(".smile-absolute-loader").css('visibility','hidden');
			}
		});
	}
	else {
		return false;
	}
});
