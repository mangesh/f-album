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
                var img;
                var li;
                $.each(result, function(p,i){
                    console.log(i);
                    /*img = $('<img>');
                    img.attr('src',i.picture).attr('alt','image'+p);*/
                    li = $('<li><img src="'+i.picture+'"></li>');
                    li.appendTo('.slides');
                })
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
                $('#openModal').click();
            }).fail(function () {
            }).always(function () {
            })
        })
    }
    /*
    {
        afterOpen: function(portfolio) {
            console.log('opened');
        }
    }
    */
    $("#openModal").animatedModal({
        modalTarget : 'slideShow',
        color       : 'black',
        afterOpen: function() {
            console.log('opened');
            $('.flexslider').flexslider({
                animation: "slide",
                width: 210,
                start: function(slider){
                  $('body').removeClass('loading');
                }
            });
        }
    });
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