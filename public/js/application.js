$(function () {
    
    $('#download-mode-group').slideUp();
    $('.upload-alert').hide();
    if($('#mode-group input[name="mode"]').length !== 0){
        $('#mode-group input[name="mode"]').on('change', function (e) {
            var _this = $(this);
            if (_this.val() == 'download') {
                $('.load-album button,.upload-album button').attr('disabled',false).removeClass('upload').addClass('download').html('Download');
                $('.upload-album').removeClass('active selected');
                $('.album').removeClass('load-album upload-album').addClass('download-album');
                $('.selected').removeClass('selected');
                $('#download-mode-group').removeClass('hide');
                $('.selected-picasa-albums').addClass('selected-albums').attr('disabled',true).removeClass('selected-picasa-albums');
                $('p.name').addClass('hide');
                $('p.button').removeClass('hide');
                $('.group-label').html('Download');
            } else if (_this.val() == 'upload'){
                $('.load-album button,.download-album button').attr('disabled',false).addClass('upload').removeClass('download').html('Upload');
                $('.download-album').removeClass('active selected');
                $('.album').removeClass('load-album download-album').addClass('upload-album');
                $('.selected').removeClass('selected');
                $('#download-mode-group').removeClass('hide');
                $('.selected-albums').addClass('selected-picasa-albums').attr('disabled',true).removeClass('selected-albums');
                $('p.name').addClass('hide');
                $('p.button').removeClass('hide');
                $('.group-label').html('Upload');
            } else {
                $('.album').removeClass('download-album upload-album').addClass('load-album');
                $('#download-mode-group').button('reset');
                $('#download-mode-group').addClass('hide');
                $('.download-album button,.upload-album button').attr('disabled',false).removeClass('download upload');
                $('.selected-picasa-albums,.selected-albums').addClass('selected-picasa-albums selected-albums');
                $('.download-album, .upload-album').removeClass('active selected');
                $('p.button').addClass('hide');
                $('p.name').removeClass('hide')
            }
            $('#albums').masonry();
        })
    }

    $(document).on('click', '.download-album img', function (e) {
        e.preventDefault();
        var _this = $(this).parents('li');

        if( e.target == this ){
            console.log(_this);
            _this.toggleClass("active").toggleClass("selected");
            var selection = new Array();
            $(".download-album.selected").each(function (ix, el) {
                selection.push($(el)[0]);
            });
            if ( selection.length > 0 ) {
                $('.download').prop('disabled', true);
                $('.selected-albums').prop('disabled', false);
            } else {
                $('.download').prop('disabled', false);
                $('.selected-albums').prop('disabled', true);
            }
        }
    })

    $(document).on('click', '.upload-album img', function (e) {
        e.preventDefault();
        var _this = $(this).parents('li');
        
        if( e.target == this ){
            _this.toggleClass("active").toggleClass("selected");
            var selection = new Array();
            $(".upload-album.selected").each(function (ix, el) {
                selection.push($(el)[0]);
            });
            if ( selection.length > 0 ) {
                $('.upload').prop('disabled', true);
                $('.selected-picasa-albums').prop('disabled', false);
            } else {
                $('.upload').prop('disabled', false);
                $('.selected-picasa-albums').prop('disabled', true);
            }
        }
    })

    if ($('.load-album').length !== 0) {
        $(document).on('click', '.load-album img', function (e) {
            e.preventDefault();
            
            $data = {
                size: 32,
                bgColor: "#fff",
                bgOpacity: 0.6,
                fontColor: "#000",
                title: '',
            };

            $.loader.open($data);
            $.ajax({
                method: "GET",
                url: "/album",
                data: { id: $(this).parents('li').attr('id') },
                dataType: "json"
            }).done(function (result) {
                var div;
                var img;
                var li;
                $('.carousel-inner').html('');
                $.each(result, function(p,i){
                    div = $('<div>');
                    div.addClass('item');
                    img = $('<img>').appendTo(div);
                    img.attr('src',i.picture).attr('alt','image'+(p+1)).addClass('img-responsive');
                    img.appendTo(div);
                    div.appendTo('.carousel-inner');
                })
                $('.carousel-inner div').eq(0).addClass('active');
                
                $('.carousel-inner .item img').css('max-height',(($( window ).height()*0.8)-60));
                var $carousel = $('.carousel').carousel({
                    interval: 3000
                }).hide();
                imagesLoaded( $('.carousel'), function() {
                    $.loader.close(true);
                    $('.carousel').show().carousel('cycle');
                    $('#openModal').modal({show:true});
                });
                
            }).fail(function () {
            }).always(function () {
            })
        })
        
        $(document).on('click', '.all-albums', function (e) {

            BootstrapDialog.confirm({
                title: 'Are you sure?',
                message: 'This may take some time!',
                type: BootstrapDialog.TYPE_WARNING,
                closable: true,
                draggable: true,
                btnCancelLabel: 'Cancel!',
                btnOKLabel: 'D0 it!',
                btnOKClass: 'btn-warning',
                callback: function(result) {
                    if(result) {
                        if($('.download-album').length > 0){
                            $('.download-album').removeClass("active").removeClass("selected");
                            $('.download-album').addClass("active").addClass("selected");
                            $('.selected-albums').prop('disabled', false);
                            $('.selected-albums').trigger('click');
                        } else {
                            $('.upload-album').removeClass("active").removeClass("selected");
                            $('.upload-album').addClass("active").addClass("selected");
                            $('.selected-picasa-albums').prop('disabled', false);
                            $('.selected-picasa-albums').trigger('click');
                        }
                    }else {
                        if($('.download-album').length > 0){
                            $('.selected-albums').prop('disabled', false);
                            $('.download-album').removeClass("active").removeClass("selected");
                        } else {
                            $('.selected-picasa-albums').prop('disabled', false);
                            $('.upload-album').removeClass("active").removeClass("selected");
                        }
                        return false;
                    }
                }
            })
        })

        $(document).on('click', 'button.download, .selected-albums', function (e) {
            e.preventDefault();
            
            $data = {
                size: 32,
                bgColor: "#fff",
                bgOpacity: 0.6,
                fontColor: "#000",
                title: '',
            };
            
            $.loader.open($data);

            if($(this).hasClass('download')){
                $('.selected').removeClass('selected');
                $(this).parents('li').addClass('selected');
            }
            
            var array = jQuery('#albums li.selected').map(function(){
                return 'id[]=' + this.id
            }).get();

            $.ajax({
                method: "GET",
                url: "/album/download",
                data: array.join('&'),
                dataType: "json"
            }).done(function (result) {
                if (result.status == 'error'){
                    BootstrapDialog.alert({
                        title: 'Error!',
                        message: result.msg,
                        type: BootstrapDialog.TYPE_WARNING,
                        closable: true,
                        draggable: true,
                        callback: function(result) {
                            $.loader.close(true);
                        }
                    })
                } else {
                    $('li.album').removeClass('active selected');
                    $('.selected-albums').attr('disabled',true);
                    $('.link-alert .link').attr('href',result.download_link);
                    $('.link-alert').show();
                    $('button.download').attr('disabled', false);
                    $.loader.close(true);
                    $( "html, body" ).animate({
                        scrollTop: 0
                    }, 800);
                }
            }).fail(function () {
                BootstrapDialog.alert({
                    title: 'Error!',
                    message: 'Technical error occured. PleaseTry again after some time.',
                    type: BootstrapDialog.TYPE_WARNING,
                    closable: true,
                    draggable: true,
                    callback: function(result) {
                        $.loader.close(true);
                    }
                })
            }).always(function () {
            })
        })

        $(document).on('click', 'p .close', function (e) {
            e.preventDefault();
            $(this).parents('p').hide();
        })

        $(document).on('click', 'button.upload, .selected-picasa-albums', function (e) {
            e.preventDefault();
            
            $data = {
                size: 32,
                bgColor: "#fff",
                bgOpacity: 0.6,
                fontColor: "#000",
                title: '',
            };
            
            $.loader.open($data);

            if($(this).hasClass('upload')){
                $('.selected').removeClass('selected');
                $(this).parents('li').addClass('selected');
            }
            
            var array = jQuery('#albums li.selected').map(function(){
                return 'id[]=' + this.id
            }).get();

            $.ajax({
                method: "GET",
                url: "/album/upload",
                data: array.join('&'),
                dataType: "json"
            }).done(function (result) {
                
                if (result.status == 'need_google_login') {
                    $.loader.close(true);
                    authorize();
                } else if (result.status == 'error'){
                    BootstrapDialog.alert({
                        title: 'Error!',
                        message: result.msg,
                        type: BootstrapDialog.TYPE_WARNING,
                        closable: true,
                        draggable: true,
                        callback: function(result) {
                            $.loader.close(true);
                        }
                    })
                } else {
                    $('li.album').removeClass('active selected');
                    $('.selected-picasa-albums').attr('disabled',true);
                    $('.upload-alert').show();
                    $( "html, body" ).animate({
                        scrollTop: 0
                    }, 800)
                }
                $.loader.close(true);
            }).fail(function () {
                BootstrapDialog.alert({
                    title: 'Error!',
                    message: 'Technical error occured. PleaseTry again after some time.',
                    type: BootstrapDialog.TYPE_WARNING,
                    closable: true,
                    draggable: true,
                    callback: function(result) {
                        $.loader.close(true);
                    }
                })
            }).always(function () {
            })
        })
    }

    var gutter = parseInt(jQuery('.album').css('marginBottom'));
    if($('#albums').length > 0){
        var $grid = $('#albums').masonry({
            percentPosition: true,
            gutter: gutter,
            itemSelector: '.album',
            columnWidth: '.album',
            isFitWidth: true
        });
        $grid.imagesLoaded().progress( function() {
            $grid.masonry();
        });
    }

    $(document).on("shown.bs.modal", ".modal", function(event) {
        if ($(".carousel").length) {
            console.log('hi');
            $(".carousel").data("bs.carousel").fit();
        }
    })
})


function authorize()
{
    var oauthWindow = window.open("https://accounts.google.com/o/oauth2/auth?scope=https://picasaweb.google.com/data/&response_type=code&access_type=offline&redirect_uri=http://"+window.location.hostname+"/google_callback&approval_prompt=force&client_id=548862589391-v5so882uie6k657ehpptta1p665uvscu.apps.googleusercontent.com","_blank","width=700,height=400");
    if(!oauthWindow || oauthWindow.closed || typeof oauthWindow.closed=='undefined')
    {
        BootstrapDialog.alert({
            title: 'Alert!',
            message: 'Please unblock popup window to login with google account',
            type: BootstrapDialog.TYPE_WARNING,
            closable: true,
            draggable: true,
            callback: function(result) {
                
            }
        })
    }
}

function oauth_complete()
{
    $('.selected-picasa-albums').attr('disabled',false).trigger('click');
}