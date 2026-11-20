(function ($) {

  "use strict";

    // PRE LOADER
    $(function(){
      $('.preloader').stop(true, true).fadeOut(150);
    });


    //Navigation Section
    $('.navbar-collapse a').on('click',function(){
      $(".navbar-collapse").collapse('hide');
    });


    // Owl Carousel
    $('.owl-carousel').owlCarousel({
      animateOut: 'fadeOut',
      items:1,
      loop:true,
      autoplay: !window.matchMedia('(prefers-reduced-motion: reduce)').matches,
      autoplayHoverPause: true,
      nav: true,
      navElement: 'button type="button"',
      navText: ['<span aria-hidden="true">‹</span>', '<span aria-hidden="true">›</span>'],
      onInitialized: function () {
        var carousel = this.$element;
        carousel.attr({role: 'region', 'aria-label': 'Presentación de la clínica'});
        carousel.find('.owl-prev').attr({'aria-label':'Fotografía anterior', type:'button'});
        carousel.find('.owl-next').attr({'aria-label':'Fotografía siguiente', type:'button'});
        carousel.find('.owl-dot').each(function(i) { $(this).attr({'aria-label':'Ir a fotografía ' + (i+1), type:'button'}); });
      },
    })


    $('.owl-carousel .owl-prev').attr({'aria-label':'Fotografía anterior', type:'button'});
    $('.owl-carousel .owl-next').attr({'aria-label':'Fotografía siguiente', type:'button'});
    $('.owl-carousel .owl-dot').each(function(i) { $(this).attr({'aria-label':'Ir a fotografía ' + (i+1), type:'button'}); });

    // PARALLAX EFFECT
    $.stellar();  


    // SMOOTHSCROLL
    $(function() {
      $('.navbar-default a[href^="#"], #home a[href^="#"], footer a[href^="#"]').on('click', function(event) {
        var $anchor = $(this);
          var href = $anchor.attr('href');
          if (!href || href === '#' || !$(href).length) return;
          $('html, body').stop().animate({
            scrollTop: $($anchor.attr('href')).offset().top - 49
          }, 1000);
            event.preventDefault();
      });
    });  


    // WOW ANIMATION
    new WOW({ mobile: false }).init();

})(jQuery);
