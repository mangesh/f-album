$(function () {
    $('#portfolio').hide();
    if ($('#javascript-ajax-button').length !== 0) {
        $('#javascript-ajax-button').on('click', function () {
            $.ajax("/songs/ajaxGetStats").done(function (result) {
                $('#javascript-ajax-result-box').html(result)
            }).fail(function () {
            }).always(function () {
            })
        })
    }

    if ($('.load-album').length !== 0) {
        $('.load-album').on('click', function (e) {
            e.preventDefault();
            $.ajax({
                method: "GET",
                url: "/album",
                data: { id: $(this).attr('id') },
                dataType: "json"
            }).done(function (result) {
                console.log(result);
                var div;
                var img;
                var li;
                $('.carousel-inner').html('');
                $.each(result, function(p,i){
                    console.log(i);
                    div = $('<div>');
                    div.addClass('item');
                    img = $('<img>').appendTo(div);
                    img.attr('src',i.picture).attr('alt','image'+p).addClass('img-responsive');
                    img.appendTo(div);
                    div.appendTo('.carousel-inner');
                    /*li = $('<li><img src="'+i.picture+'"></li>');
                    li.appendTo('.slides');*/
                })
                $('.carousel-inner div').eq(0).addClass('active');
                /*var o = {
                    init: function(){
                        this.portfolio.init();
                    },
                    portfolio: {
                        data: {
                        },
                        init: function(){
                            $('#portfolio').portfolio(o.portfolio.data);
                        }
                    }
                }

                $(function(){ o.init(); });*/
                /*$('#openModal').click();*/
                //$('.modal-content').css('max-height',$( window ).height()*0.8);
                $('.carousel-inner .item img').css('max-height',$( window ).height()*0.8);
                $('#openModal').modal({show:true});
                
            }).fail(function () {
            }).always(function () {
            })
        })
    }

    $('#openModal').on('shown.bs.modal', function () {
        $('.carousel').carousel();
        //centerModals($(this));
        // var carousel = $(this).find('.carousel').hide();
        // var deferreds = [];
        // var imgs = $('.carousel', this).find('img');
        // // loop over each img
        // console.log('open event');
        // imgs.each(function(){
        //     var self = $(this);
        //     var datasrc = self.attr('data-src');
        //     if (datasrc) {
        //         var d = $.Deferred();
        //         self.one('load', d.resolve)
        //             .attr("src", datasrc)
        //             .attr('data-src', '');
        //         deferreds.push(d.promise());
        //     }
        // });

        // $.when.apply($, deferreds).done(function(){
        //     /*$('#ajaxloader').hide();*/
        //     console.log('open deferreds');
        //     carousel.fadeIn(1000);
        // });
    });

    /* center modal */
    function centerModals($element) {
        var $modals;
        if ($element.length) {
            $modals = $element;
        } else {
            $modals = $('.modal-vcenter:visible');
        }
        $modals.each( function(i) {
            var $clone = $(this).clone().css('display', 'block').appendTo('body');
            var top = Math.round(($clone.height() - $clone.find('.modal-content').height()) / 2);
            top = top > 0 ? top : 0;
            $clone.remove();
            $(this).find('.modal-content').css("margin-top", top);
        });
    }

    //$(window).on('resize', centerModals);
    /*
    {
        afterOpen: function(portfolio) {
            console.log('opened');
        }
    }
    */
    /*$("#openModal").animatedModal({
        modalTarget : 'slideShow',
        color       : 'black',
        afterOpen: function() {
            console.log('opened');
            $('.carousel').carousel();
            $('.flexslider').flexslider({
                animation: "slide",
                width: 210,
                start: function(slider){
                  $('body').removeClass('loading');
                }
            });
        }
    });*/
})

// Load is used to ensure all images have been loaded, impossible with document
jQuery(window).load(function () {
    // Takes the gutter width from the bottom margin of .album
    var gutter = parseInt(jQuery('.album').css('marginBottom'));
    var container = jQuery('#albums');
    // Creates an instance of Masonry on #albums
    container.masonry({
        gutter: gutter,
        itemSelector: '.album',
        columnWidth: '.album'
    });
    // This code fires every time a user resizes the screen and only affects .album elements
    // whose parent class isn't .container. Triggers resize first so nothing looks weird.
    jQuery(window).bind('resize', function () {
        if (!jQuery('#albums').parent().hasClass('container')) {
            // Resets all widths to 'auto' to sterilize calculations
            post_width = jQuery('.album').width() + gutter;
            jQuery('#albums, body > #grid').css('width', 'auto');
            // Calculates how many .album elements will actually fit per row. Could this code be cleaner?
            posts_per_row = jQuery('#albums').innerWidth() / post_width;
            floor_posts_width = (Math.floor(posts_per_row) * post_width) - gutter;
            ceil_posts_width = (Math.ceil(posts_per_row) * post_width) - gutter;
            posts_width = (ceil_posts_width > jQuery('#albums').innerWidth()) ? floor_posts_width : ceil_posts_width;
            if (posts_width == jQuery('.album').width()) {
                posts_width = '100%';
            }// Ensures that all top-level elements have equal width and stay centered
            jQuery('#albums, #grid').css('width', posts_width);
            jQuery('#grid').css({'margin': '0 auto'});
        }
    }).trigger('resize');
});