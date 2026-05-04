$(function () {

    var $sidebar  = $('#sidebar');
    var $main     = $('#mainContent');
    var $overlay  = $('#sidebarOverlay');
    var MOBILE_BP = 768;

    function isMobile() {
        return $(window).width() < MOBILE_BP;
    }

    // Set initial state based on screen size
    function initSidebar() {
        if (isMobile()) {
            $sidebar.addClass('sidebar-hidden');
            $main.css('margin-left', '0');
        } else {
            $sidebar.removeClass('sidebar-hidden');
            $main.css('margin-left', '14rem'); // 224px = w-56
        }
    }

    initSidebar();

    // Re-init on resize
    $(window).on('resize', function () {
        initSidebar();
        // Close overlay on resize to desktop
        if (!isMobile()) {
            $overlay.removeClass('active');
        }
    });

    // Hamburger toggle
    $('#sidebarToggle').on('click', function () {
        var isHidden = $sidebar.hasClass('sidebar-hidden');

        if (isHidden) {
            // Open sidebar
            $sidebar.removeClass('sidebar-hidden');
            if (isMobile()) {
                $overlay.addClass('active'); // show overlay on mobile
            } else {
                $main.css('margin-left', '14rem');
            }
        } else {
            // Close sidebar
            $sidebar.addClass('sidebar-hidden');
            $overlay.removeClass('active');
            if (!isMobile()) {
                $main.css('margin-left', '0');
            }
        }
    });

    // Tap overlay to close sidebar on mobile
    $overlay.on('click', function () {
        $sidebar.addClass('sidebar-hidden');
        $overlay.removeClass('active');
    });

    // User dropdown
    $('#userMenuBtn').on('click', function (e) {
        e.stopPropagation();
        $('#userMenu').toggleClass('hidden');
    });
    $(document).on('click', function () {
        $('#userMenu').addClass('hidden');
    });

    // Sidebar submenu accordion
    $('.sidebar-group-btn').on('click', function () {
        var $btn     = $(this);
        var $submenu = $btn.next('.sidebar-submenu');
        var $chevron = $btn.find('.chevron');

        $('.sidebar-submenu').not($submenu).slideUp(200);
        $('.chevron').not($chevron).removeClass('rotate-180');

        $submenu.slideToggle(200);
        $chevron.toggleClass('rotate-180');
    });

    // Auto-open active submenu based on current URL
    var path = window.location.pathname;
    $('.sidebar-submenu a').each(function () {
        var href = $(this).attr('href');
        if (href && href !== '#' && path.indexOf(href.replace(window.location.origin, '')) !== -1) {
            $(this).closest('.sidebar-submenu').show();
            $(this).closest('.sidebar-group').find('.chevron').addClass('rotate-180');
            $(this).addClass('text-blue-700 font-medium');
        }
    });

    // Auto-dismiss flash messages
    setTimeout(function () {
        $('[data-flash]').fadeOut(400);
    }, 4000);

});
