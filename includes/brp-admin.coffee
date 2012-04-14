$ = jQuery

brpAdmin =
  init: ->
    @message = $('#message')
    @cacheTimestamp = $('#brp-cache-timestamp')
    @refreshCache =
      button: $('#brp-refresh-cache-button'),
      spinner: $('#brp-refresh-cache-spinner')
    @refreshCache.nonce = @refreshCache.button.data('nonce')
    @bindClicks()
  bindClicks: ->
    brpAdmin = this
    @refreshCache.button.click (e) =>
      e.preventDefault();
      @message.hide()
      @refreshCache.spinner.css('visibility', 'visible')
      data =
        action: 'brp-refresh-cache',
        brpNonce: @refreshCache.nonce
      $.post ajaxurl, data, (response) =>
        @refreshCache.spinner.css('visibility', 'hidden')
        if response.status == 'success'
          @message
            .attr('class', 'updated raptr')
            .html('<p>Cache refreshed!</p>')
            .show()
          @cacheTimestamp.text(response.data.timestamp)
        else
          @message
            .attr('class', 'error raptr')
            .html('<p>' + response.data + '</p>')
            .show()
    $('a.brp-cache-images-button').click (e) ->
      e.preventDefault()
      spinner = $($(this).siblings('img.brp-cache-images-spinner'))
      data =
        action: 'brp-cache-images',
        brpNonce: $(this).data('nonce'),
        cacheIndex: $(this).data('index')
      brpAdmin.message.hide()
      spinner.css('visibility', 'visible')
      $.post ajaxurl, data, (response) ->
        spinner.css('visibility', 'hidden')
        if response.status == 'success'
          brpAdmin.message
            .attr('class', 'updated raptr')
            .html('<p>Images cached!</p>')
            .show()
          brpAdmin.cacheTimestamp.text(response.data.timestamp);
          $('#raptr-boxart-sm-' + data.cacheIndex).removeAttr('src').attr('src', response.data.img_small);
          $('#raptr-boxart-md-' + data.cacheIndex).removeAttr('src').attr('src', response.data.img_med);
        else
          brpAdmin.message
            .attr('class', 'error raptr')
            .html('<p>' + response.data + '</p>')
            .show();

$ ->
  brpAdmin.init()

# jQuery(function() {
#   var $ = jQuery;
#   var brpAdminMessage = $('#message');
#   var brpCacheTimestamp = $('#brp-cache-timestamp');
#   brpRefreshCache = {
#     button: $('#brp-refresh-cache-button'),
#     spinner: $('#brp-refresh-cache-spinner')
#   };
#   brpRefreshCache.nonce = brpRefreshCache.button.data('nonce');
#   brpRefreshCache.button.bind('click', function(e) {
#     e.preventDefault();
#     brpAdminMessage.hide();
#     brpRefreshCache.spinner.css('visibility', 'visible');
#     var data = {
#       action: 'brp-refresh-cache',
#       brpNonce: brpRefreshCache.nonce
#     };
#     $.post(ajaxurl, data, function(response) {
#       brpRefreshCache.spinner.css('visibility', 'hidden');
#       if (response.status == 'success') {
#         brpAdminMessage.attr('class', 'updated raptr').html('<p>Cache refreshed!</p>').show();
#         brpCacheTimestamp.text(response.data.timestamp);
#       }
#       else {
#         brpAdminMessage.attr('class', 'error raptr').html('<p>' + response.data + '</p>').show();
#       }
#     });
#   });
#   $('a.brp-cache-images-button').bind('click', function(e) {
#     e.preventDefault();
#     brpAdminMessage.hide();
#     var spinner = $($(this).siblings('img.brp-cache-images-spinner'));
#     spinner.css('visibility', 'visible');
#     var data = {
#       action: 'brp-cache-images',
#       brpNonce: $(this).data('nonce'),
#       cacheIndex: $(this).data('index')
#     };
#     $.post(ajaxurl, data, function(response) {
#       spinner.css('visibility', 'hidden');
#       if (response.status == 'success') {
#         brpAdminMessage.attr('class', 'updated raptr').html('<p>Images cached!</p>').show();
#         brpCacheTimestamp.text(response.data.timestamp);
#         $('#raptr-boxart-sm-' + data.cacheIndex).removeAttr('src').attr('src', response.data.img_small);
#         $('#raptr-boxart-md-' + data.cacheIndex).removeAttr('src').attr('src', response.data.img_med);
#       }
#       else {
#         brpAdminMessage.attr('class', 'error raptr').html('<p>' + response.data + '</p>').show();
#       }
#     });
#   });
# });