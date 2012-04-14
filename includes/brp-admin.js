(function() {
  var $, brpAdmin;

  $ = jQuery;

  brpAdmin = {
    init: function() {
      this.message = $('#message');
      this.cacheTimestamp = $('#brp-cache-timestamp');
      this.refreshCache = {
        button: $('#brp-refresh-cache-button'),
        spinner: $('#brp-refresh-cache-spinner')
      };
      this.refreshCache.nonce = this.refreshCache.button.data('nonce');
      return this.bindClicks();
    },
    bindClicks: function() {
      var _this = this;
      brpAdmin = this;
      this.refreshCache.button.click(function(e) {
        var data;
        e.preventDefault();
        _this.message.hide();
        _this.refreshCache.spinner.css('visibility', 'visible');
        data = {
          action: 'brp-refresh-cache',
          brpNonce: _this.refreshCache.nonce
        };
        return $.post(ajaxurl, data, function(response) {
          _this.refreshCache.spinner.css('visibility', 'hidden');
          if (response.status === 'success') {
            _this.message.attr('class', 'updated raptr').html('<p>Cache refreshed!</p>').show();
            return _this.cacheTimestamp.text(response.data.timestamp);
          } else {
            return _this.message.attr('class', 'error raptr').html('<p>' + response.data + '</p>').show();
          }
        });
      });
      return $('a.brp-cache-images-button').click(function(e) {
        var data, spinner;
        e.preventDefault();
        spinner = $($(this).siblings('img.brp-cache-images-spinner'));
        data = {
          action: 'brp-cache-images',
          brpNonce: $(this).data('nonce'),
          cacheIndex: $(this).data('index')
        };
        brpAdmin.message.hide();
        spinner.css('visibility', 'visible');
        return $.post(ajaxurl, data, function(response) {
          spinner.css('visibility', 'hidden');
          if (response.status === 'success') {
            brpAdmin.message.attr('class', 'updated raptr').html('<p>Images cached!</p>').show();
            brpAdmin.cacheTimestamp.text(response.data.timestamp);
            $('#raptr-boxart-sm-' + data.cacheIndex).removeAttr('src').attr('src', response.data.img_small);
            return $('#raptr-boxart-md-' + data.cacheIndex).removeAttr('src').attr('src', response.data.img_med);
          } else {
            return brpAdmin.message.attr('class', 'error raptr').html('<p>' + response.data + '</p>').show();
          }
        });
      });
    }
  };

  $(function() {
    return brpAdmin.init();
  });

}).call(this);
