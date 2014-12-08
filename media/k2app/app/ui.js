(function($) {

	// Container
	var K2Container = jQuery('[data-application="k2"]');

	// Modals
	K2Container.on('click', 'a[data-role="modal"]', function(event) {
		event.preventDefault();
		var src = jQuery(this).attr('href');
		var type = jQuery(this).data('type') || 'image';
		require(['magnific', 'css!magnificStyle'], _.bind(function() {
			jQuery.magnificPopup.open({
				items : {
					src : src
				},
				type : type
			});
		}, this));
	});

	// Hack to trigger the select boxes when their label is clicked
	K2Container.on('click', '[data-region="filters"] label', function(event) {
		var element = jQuery(this).next();
		if (element.hasClass('select2-container')) {
			if (element.hasClass('select2-dropdown-open')) {
				element.select2('close');
			} else {
				element.select2('open');
			}
		}
	});

	// Anchors
	K2Container.on('click', '.jw--scrollspymenu a', function(event) {
		event.preventDefault();
		var topMenuHeight = 15;
		var target = jQuery(this).attr('href');
		var offsetTop = 0;
		if (target !== '#') {
			offsetTop = jQuery(target).offset().top - topMenuHeight - 140;
		}
		jQuery('html, body').stop().animate({
			scrollTop : offsetTop
		}, 300);
	});

	// resetting the filters
	K2Container.on('click', '#jw--filters--reset', function() {
		$('.jw--filter .jw--radio').removeClass('jw--radio__checked');
		$('.jw--filter .jw--radio[for="state_0"]').addClass('jw--radio__checked');
		$('.jw--filter .jw--radio[for="featured_0"]').addClass('jw--radio__checked');
	});
	
	// Make sure the menu works with hovering and clicking
	K2Container.on('click', '.jw--main--menu li.jw--haschild', function() {
		$(this).toggleClass('jw--activechild');
		$(this).children('ul').toggleClass('jw--visible');
	});
	
	// Close the sidebar if the user clicks on the info/settings view
	K2Container.on('click', '.jw--sidebar--header a', function() {
		if ($('.jw--sidebar').hasClass('jw--sidebar__open')) {
			$('.jw--sidebar').removeClass('jw--sidebar__open');
			K2Container.removeClass('open--sidebar');
		}		
	});
	
	// Menu toggler
	K2Container.on('click', '#jw--navtoggle', function(e) {
		e.preventDefault();
		$('.jw--main--menu').toggleClass('menu--open');
	});
	
	$(window).on('k2AdminListRendered', function() {
		$('div[data-region="grid"], div[data-region="list"]').css('min-height', $(window).height() - 80 );
	});
	
})(jQuery);