/***************************************************
==================== JS INDEX ======================
****************************************************
Data js
Sticky Nav Js
Mobile Menu Js
Search Bar Js
Project Slider Js
Project Slider 2 Js
Testimonial Slider Js
Testimonial Slider 2 Js
Testimonial Slider 3 Js
Hero slider Js
Blog Slider Js
Accordion Js
Project Hover active
Backtotop Js
Odometer js
Progressbar js
GSAP Animations
****************************************************/

(function($) {
    "use strict";

    ////////////////////////////////////////////////////
    // Data js
    $("[data-bg-image]").each(function() {
        var $this = $(this),
            $image = $this.data("bg-image");
        $this.css("background-image", "url(" + $image + ")");
    });

    ////////////////////////////////////////////////////
    // Sticky Nav Js
    var lastScrollTop = 0;

    function stickyMenu($targetMenu, $toggleClass) {
        var st = $(window).scrollTop();

        if (st > 500) {
            if (st > lastScrollTop) {
                $targetMenu.removeClass($toggleClass);
            } else {
                $targetMenu.addClass($toggleClass);
            }
        } else {
            $targetMenu.removeClass($toggleClass);
        }

        lastScrollTop = st;
    }

    $(window).on("scroll", function() {
        if ($(".header-area").length && $(".header-sticky").length) {
            stickyMenu($(".header-sticky"), "sticky");
        }
    });

    ////////////////////////////////////////////////////
    // Mobile Menu Js
    $(".mobile_menu_bar").on("click", function() {
        $(this).toggleClass("on");
        $(".hamburger-area").addClass("opened");
        $(".body-overlay").addClass("opened");
        $("body").addClass("overflow-hidden");
    });

    if ($("#mobile-menu").length && $.fn.meanmenu) {
        $("#mobile-menu").meanmenu({
            meanMenuContainer: ".mobile_menu",
            meanScreenWidth: "991",
            meanExpand: ['<i class="tji-arrow-down"></i>']
        });
    }

    // Offcanvas js
    $(".menu_offcanvas").on("click", function() {
        $(".tj-offcanvas-area").addClass("opened");
        $(".body-overlay").addClass("opened");
        $("body").addClass("overflow-hidden");
    });

    $(".hamburger_close_btn").on("click", function() {
        $(".tj-offcanvas-area").removeClass("opened");
        $(".hamburger-area").removeClass("opened");
        $(".body-overlay").removeClass("opened");
        $("body").removeClass("overflow-hidden");
        $(".mobile_menu_bar").removeClass("on");
    });

    $(".body-overlay").on("click", function() {
        $(".tj-offcanvas-area").removeClass("opened");
        $(".hamburger-area").removeClass("opened");
        $(".body-overlay").removeClass("opened");
        $("body").removeClass("overflow-hidden");
        $(".mobile_menu_bar").removeClass("on");
    });

    ////////////////////////////////////////////////////
    // Search Bar Js
    $(".header-search .search").on("click", function() {
        $(this).addClass("search-hide");
        $(".search_close_btn").addClass("close-show");
        $(".search_popup").addClass("search-opened");
        $(".search-popup-overlay").addClass("search-popup-overlay-open");
    });

    $(".search_close_btn, .search-popup-overlay").on("click", function() {
        $(".header-search .search").removeClass("search-hide");
        $(".search_popup").removeClass("search-opened");
        $(".search-popup-overlay").removeClass("search-popup-overlay-open");
        $(".search_close_btn").removeClass("close-show");
    });

    ////////////////////////////////////////////////////
    // Nice Select Js (skip custom registration-form controls)
    if ($("select").length > 0 && $.fn.niceSelect) {
        $("select").not(".reg-control, .reg-native").niceSelect();
    }

    ////////////////////////////////////////////////////
    // Project Slider Js
    if ($(".project-slider").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".project-slider", {
            slidesPerView: 3,
            spaceBetween: 30,
            centeredSlides: true,
            loop: true,
            autoplay: {
                delay: 6000
            },
            speed: 1500,
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            },
            breakpoints: {
                0: {
                    slidesPerView: 1.2,
                    spaceBetween: 15
                },
                576: {
                    slidesPerView: 1.7,
                    spaceBetween: 20
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 20
                },
                992: {
                    slidesPerView: 2
                },
                1024: {
                    slidesPerView: 2.2
                },
                1400: {
                    slidesPerView: 2.31
                }
            }
        });
    }

    // Project Slider 2 Js
    if ($(".project-slider-2").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".project-slider-2", {
            slidesPerView: 4,
            spaceBetween: 30,
            loop: true,
            speed: 1500,
            navigation: {
                nextEl: ".slider-next",
                prevEl: ".slider-prev"
            },
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            },
            breakpoints: {
                0: {
                    slidesPerView: 1.2,
                    spaceBetween: 15
                },
                580: {
                    slidesPerView: 2,
                    spaceBetween: 20
                },
                992: {
                    slidesPerView: 3,
                    spaceBetween: 20
                },
                1500: {
                    slidesPerView: 4,
                    spaceBetween: 30
                }
            }
        });
    }

    // Project Slider 3 Js
    if ($(".project-slider-3").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".project-slider-3", {
            slidesPerView: 3,
            spaceBetween: 30,
            centeredSlides: false,
            loop: true,
            autoplay: {
                delay: 6000
            },
            speed: 1500,
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            },
            breakpoints: {
                0: {
                    slidesPerView: 1.2,
                    spaceBetween: 15
                },
                576: {
                    slidesPerView: 1.5,
                    spaceBetween: 20
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 20
                },
                992: {
                    slidesPerView: 2.4
                },
                1200: {
                    slidesPerView: 3
                }
            }
        });
    }

    ////////////////////////////////////////////////////
    // Testimonial Slider Js
    if ($(".testimonial-slider").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".testimonial-slider", {
            slidesPerView: 3,
            spaceBetween: 28,
            centeredSlides: true,
            loop: true,
            speed: 1500,
            autoplay: {
                delay: 3000
            },
            navigation: {
                nextEl: ".slider-next",
                prevEl: ".slider-prev"
            },
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            },
            breakpoints: {
                0: {
                    slidesPerView: 1.2,
                    spaceBetween: 15,
                    centeredSlides: false
                },
                576: {
                    slidesPerView: 1.3,
                    spaceBetween: 20,
                    centeredSlides: false
                },
                768: {
                    slidesPerView: 1.4,
                    spaceBetween: 20,
                    centeredSlides: false
                },
                992: {
                    slidesPerView: 3
                },
                1200: {
                    slidesPerView: 3
                }
            }
        });
    }

    // Testimonial Slider 2 Js
    if ($(".testimonial-slider-2").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".testimonial-slider-2", {
            slidesPerView: 1,
            spaceBetween: 28,
            loop: true,
            speed: 1500,
            autoplay: {
                delay: 3000
            },
            navigation: {
                nextEl: ".slider-next",
                prevEl: ".slider-prev"
            },
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            }
        });
    }

    // Vertical testimonial slider
    var verticalTestimonialSlider8 = null;

    function verticalTestimonialSlider() {
        if (typeof Swiper === "undefined") return;

        var screenWidth = window.innerWidth;
        var direction = screenWidth >= 992 ? "vertical" : "horizontal";

        if (verticalTestimonialSlider8) {
            verticalTestimonialSlider8.destroy(true, true);
            verticalTestimonialSlider8 = null;
        }

        if ($(".h6-testimonial-slider").length > 0) {
            verticalTestimonialSlider8 = new Swiper(".h6-testimonial-slider", {
                direction: direction,
                slidesPerView: 1,
                spaceBetween: 20,
                loop: true,
                speed: 1000,
                autoplay: {
                    delay: 5000
                },
                breakpoints: {
                    576: {
                        slidesPerView: 1.2
                    },
                    992: {
                        slidesPerView: "auto",
                        spaceBetween: 30
                    }
                }
            });
        }
    }

    verticalTestimonialSlider();

    window.addEventListener("resize", function() {
        verticalTestimonialSlider();
    });

    // Testimonial Slider 3 Js
    if ($(".client-thumb").length > 0 && $(".testimonial-slider-3").length > 0 && typeof Swiper !== "undefined") {
        var thumbSlider3 = new Swiper(".client-thumb", {
            slidesPerView: 3,
            spaceBetween: 12,
            loop: true,
            speed: 1500,
            centeredSlides: true,
            freeMode: true,
            watchSlidesProgress: true,
            slideToClickedSlide: true
        });

        var testimonialSlider3 = new Swiper(".testimonial-slider-3", {
            slidesPerView: "auto",
            spaceBetween: 28,
            loop: true,
            speed: 1500,
            autoplay: {
                delay: 3000
            },
            navigation: {
                nextEl: ".slider-next",
                prevEl: ".slider-prev"
            },
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            }
        });

        testimonialSlider3.controller.control = thumbSlider3;
        thumbSlider3.controller.control = testimonialSlider3;
    }

    // Testimonial 5 Slider Js
    if ($(".h5-testimonial-slider").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".h5-testimonial-slider", {
            slidesPerView: 1.2,
            spaceBetween: 20,
            centeredSlides: true,
            loop: true,
            speed: 1500,
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            },
            autoplay: {
                delay: 7000,
                disableOnInteraction: true
            },
            breakpoints: {
                992: {
                    centeredSlides: false,
                    slidesPerView: 2,
                    spaceBetween: 30
                }
            }
        });
    }

    ////////////////////////////////////////////////////
    // Hero slider Js
    var heroThumbSwiper = null;

    if ($(".hero-thumb").length > 0 && typeof Swiper !== "undefined") {
        heroThumbSwiper = new Swiper(".hero-thumb", {
            loop: false,
            spaceBetween: 15,
            slidesPerView: 3,
            freeMode: true,
            watchSlidesProgress: true
        });
    }

    if ($(".hero-slider").length > 0 && typeof Swiper !== "undefined") {
        var heroOptions = {
            slidesPerView: 1,
            spaceBetween: 0,
            effect: "fade",
            loop: true,
            speed: 1400,
            autoplay: {
                delay: 5000
            },
            navigation: {
                nextEl: ".slider-next",
                prevEl: ".slider-prev"
            }
        };

        if (heroThumbSwiper) {
            heroOptions.thumbs = {
                swiper: heroThumbSwiper
            };
        }

        new Swiper(".hero-slider", heroOptions);
    }

    if ($(".h6-hero-card-slider").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".h6-hero-card-slider", {
            slidesPerView: 1,
            spaceBetween: 15,
            loop: true,
            speed: 1400,
            autoplay: {
                delay: 5000
            },
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            }
        });
    }

    ////////////////////////////////////////////////////
    // Blog Slider Js
    if ($(".blog-slider").length > 0 && typeof Swiper !== "undefined") {
        new Swiper(".blog-slider", {
            slidesPerView: 2,
            spaceBetween: 30,
            loop: true,
            speed: 1500,
            autoplay: {
                delay: 2000
            },
            navigation: {
                nextEl: ".slider-next",
                prevEl: ".slider-prev"
            },
            pagination: {
                el: ".swiper-pagination-area",
                clickable: true
            },
            breakpoints: {
                0: {
                    slidesPerView: 1,
                    spaceBetween: 15
                },
                576: {
                    slidesPerView: 1,
                    spaceBetween: 15
                },
                768: {
                    slidesPerView: 1,
                    spaceBetween: 20
                },
                992: {
                    slidesPerView: 2,
                    spaceBetween: 20
                },
                1200: {
                    slidesPerView: 2
                }
            }
        });
    }

    ////////////////////////////////////////////////////
    // Accordion Js
    if ($(".accordion-item").length > 0) {
        $(".accordion-item .faq-title").on("click", function() {
            if ($(this).parent().hasClass("active")) {
                $(this).parent().removeClass("active");
            } else {
                $(this).parent().siblings().removeClass("active");
                $(this).parent().addClass("active");
            }
        });
    }

    ////////////////////////////////////////////////////
    // Backtotop Js
    function back_to_top() {
        var btn = $("#back_to_top");
        var btn_wrapper = $(".back-to-top-wrapper");

        $(window).on("scroll", function() {
            if ($(window).scrollTop() > 300) {
                btn_wrapper.addClass("back-to-top-btn-show");
            } else {
                btn_wrapper.removeClass("back-to-top-btn-show");
            }
        });

        btn.on("click", function(e) {
            e.preventDefault();
            $("html, body").animate({ scrollTop: 0 }, 300);
        });
    }
    back_to_top();

    ////////////////////////////////////////////////////
    // Odometer js
    if ($(".odometer").length > 0 && $.fn.appear) {
        $(".odometer").each(function() {
            $(this).appear(function() {
                var numCount = $(this).attr("data-count");
                $(this).html(numCount);
            });
        });
    }

    ////////////////////////////////////////////////////
    // wow js
    if ($(".wow").length > 0 && typeof WOW !== "undefined") {
        new WOW().init();
    }

    ////////////////////////////////////////////////////
    // Project Hover active change
    if ($(".team-wrapper").length) {
        $(".team-item").on("mouseover", function() {
            $(this).siblings(".team-item").removeClass("active");
            $(this).addClass("active");

            var newSrc = $(this).data("src");
            var $image = $(".team-img img");

            if (newSrc && $image.length) {
                $image
                    .fadeOut(300)
                    .css("transform", "scale(1.1)")
                    .promise()
                    .done(function() {
                        $image.attr("src", newSrc).fadeIn(300).css("transform", "scale(1)");
                    });
            }
        });
    }

    ////////////////////////////////////////////////////
    // Progress bar
    function progressBarController() {
        var progressContainers = document.querySelectorAll(".tj-progress");

        if (!progressContainers.length) return;

        progressContainers.forEach(function(progressContainer) {
            var targetedProgressBar = progressContainer.querySelector(".tj-progress-bar");
            if (!targetedProgressBar) return;

            var completedPercent = parseInt(targetedProgressBar.getAttribute("data-percent"), 10) || 0;

            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        targetedProgressBar.style.transition = "width 2s ease-out";
                        targetedProgressBar.style.width = completedPercent + "%";

                        var percentageText = progressContainer.querySelector(".tj-progress-percent");
                        if (percentageText) {
                            var currentPercent = 0;
                            var interval = setInterval(function() {
                                currentPercent++;
                                percentageText.textContent = currentPercent + "%";

                                if (currentPercent >= completedPercent) {
                                    clearInterval(interval);
                                }
                            }, 15);
                        }

                        observer.unobserve(progressContainer);
                    }
                });
            }, {
                root: null,
                threshold: [0.3, 0.9]
            });

            observer.observe(progressContainer);
        });
    }

    progressBarController();

    ////////////////////////////////////////////////////
    // GSAP Js
    if (typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
        gsap.registerPlugin(ScrollTrigger);
        gsap.config({
            nullTargetWarn: false
        });

        var stickySidebarTween = null;

        function initStickySidebar() {
            if (stickySidebarTween && stickySidebarTween.scrollTrigger) {
                stickySidebarTween.scrollTrigger.kill();
                stickySidebarTween.kill();
                stickySidebarTween = null;
            }

            if (!$(".tj-sticky").length) return;

            if (window.innerWidth >= 992) {
                var startPoint = 100;

                stickySidebarTween = gsap.to(".tj-sticky", {
                    scrollTrigger: {
                        trigger: ".tj-sticky",
                        start: "top " + startPoint,
                        end: "bottom " + startPoint,
                        pin: true,
                        scrub: 1
                    }
                });
            }
        }

        initStickySidebar();

        window.addEventListener("resize", function() {
            initStickySidebar();
        });

        var device_width = window.innerWidth;
        var serviceStack = gsap.utils.toArray(".service-stack");

        if (serviceStack.length > 0 && device_width > 767) {
            serviceStack.forEach(function(item) {
                gsap.to(item, {
                    opacity: 0,
                    scale: 0.9,
                    y: 50,
                    scrollTrigger: {
                        trigger: item,
                        scrub: true,
                        start: "top top",
                        pin: true,
                        pinSpacing: false,
                        markers: false
                    }
                });
            });
        }

        var h6ProjectStack = gsap.utils.toArray(".project-stack");

        if (h6ProjectStack.length > 0 && device_width > 991) {
            h6ProjectStack.forEach(function(item) {
                gsap.to(item, {
                    scrollTrigger: {
                        trigger: item,
                        scrub: true,
                        start: "top top",
                        pin: true,
                        pinSpacing: false,
                        markers: false
                    }
                });
            });
        }

        if ($(".tj-scroll-slider-item").length > 0) {
            var mediaMatch = gsap.matchMedia();

            mediaMatch.add("(min-width: 768px)", function() {
                var slider = document.querySelector(".tj-scroll-slider");

                if (slider && slider.children && slider.children.length) {
                    var panels = gsap.utils.toArray(".tj-scroll-slider-item");

                    gsap.to(panels, {
                        xPercent: -100 * (panels.length - 1),
                        ease: "none",
                        scrollTrigger: {
                            trigger: slider,
                            start: "top+=50 top",
                            pin: true,
                            scrub: 1,
                            end: function() {
                                return "+=" + slider.offsetWidth;
                            }
                        }
                    });

                    setTimeout(function() {
                        ScrollTrigger.refresh();
                    }, 500);
                }
            });
        }

        if ($(".text-anim").length && typeof SplitText !== "undefined") {
            var animatedTextElements = document.querySelectorAll(".text-anim");

            animatedTextElements.forEach(function(element) {
                var animationSplitText = new SplitText(element, { type: "chars, words" });

                gsap.from(animationSplitText.chars, {
                    duration: 1,
                    delay: 0.1,
                    x: 20,
                    autoAlpha: 0,
                    stagger: 0.02,
                    ease: "power2.out",
                    scrollTrigger: { trigger: element, start: "top 85%" }
                });
            });
        }

        if ($(".title-anim").length && typeof SplitText !== "undefined") {
            var animatedTitleElements = document.querySelectorAll(".title-anim");

            animatedTitleElements.forEach(function(element) {
                var splitTitle = new SplitText(element, {
                    types: "lines, words, chars"
                });

                gsap.from(splitTitle.chars, {
                    y: "100%",
                    duration: 0.5,
                    delay: 0.1,
                    autoAlpha: 0,
                    stagger: 0.01,
                    ease: "power1.inout",
                    scrollTrigger: { trigger: element, start: "top 85%" }
                });
            });
        }

        if ($(".marquee-slider-wrapper-two").length > 0 && $(".tj-project-section-two").length > 0) {
            gsap.to(".marquee-slider-wrapper-two", {
                scrollTrigger: {
                    trigger: ".tj-project-section-two",
                    start: "top center-=200",
                    pin: ".marquee-slider-wrapper-two",
                    end: "bottom bottom-=200",
                    markers: false,
                    pinSpacing: false,
                    scrub: 1
                }
            });
        }

        document.querySelectorAll(".rightSwipeWrap").forEach(function(wrap, i) {
            gsap.set(wrap.querySelectorAll(".right-swipe"), {
                transformPerspective: 1200,
                x: "10rem",
                rotateY: -20,
                opacity: 0,
                transformOrigin: "right center"
            });

            gsap.to(wrap.querySelectorAll(".right-swipe"), {
                transformPerspective: 1200,
                x: 0,
                rotateY: 0,
                opacity: 1,
                delay: 0.3,
                ease: "power3.out",
                scrollTrigger: {
                    trigger: wrap,
                    start: "top 80%",
                    id: "rightSwipeWrap-" + i,
                    toggleActions: "play none none none"
                }
            });
        });

        document.querySelectorAll(".leftSwipeWrap").forEach(function(wrap, i) {
            gsap.set(wrap.querySelectorAll(".left-swipe"), {
                transformPerspective: 1200,
                x: "-10rem",
                rotateY: 20,
                opacity: 0,
                transformOrigin: "left center"
            });

            gsap.to(wrap.querySelectorAll(".left-swipe"), {
                transformPerspective: 1200,
                x: 0,
                rotateY: 0,
                opacity: 1,
                delay: 0.4,
                ease: "power3.out",
                scrollTrigger: {
                    trigger: wrap,
                    start: "top 80%",
                    id: "leftSwipeWrap-" + i,
                    toggleActions: "play none none none"
                }
            });
        });

        document.querySelectorAll(".tjParallaxSection").forEach(function(section) {
            var image = section.querySelector(".tjParallaxImage");

            if (image) {
                gsap.to(image, {
                    y: "-25%",
                    ease: "none",
                    scrollTrigger: {
                        trigger: section,
                        start: "top bottom",
                        end: "bottom top",
                        scrub: true
                    }
                });
            }
        });

        var teamItems = document.querySelectorAll(".itemScrollAnimate");

        teamItems.forEach(function(item, index) {
            var isEven = index % 2 === 0;

            gsap.fromTo(
                item, {
                    transform: isEven ?
                        "perspective(1000px) rotateX(50deg)" : "perspective(1000px) rotateX(-50deg)"
                }, {
                    transform: "perspective(1000px) rotateX(0deg)",
                    duration: 2,
                    ease: "power3.out",
                    scrollTrigger: {
                        id: "teamItemTrigger-" + index,
                        trigger: item,
                        start: "top 100%",
                        end: "top 40%",
                        scrub: true
                    }
                }
            );
        });

        var $cursor = $(".tj-cursor");
        var $container = $(".project-slider-one");

        if ($container.length > 0 && $cursor.length > 0) {
            gsap.set($cursor, { xPercent: -50, yPercent: -50 });

            $(document).on("pointermove", function(e) {
                gsap.to($cursor, {
                    duration: 0,
                    x: e.clientX,
                    y: e.clientY
                });
            });

            $container.on("mouseenter", function() {
                $cursor.css({
                    opacity: 1,
                    visibility: "visible"
                });
            });

            $container.on("mouseleave", function() {
                $cursor.css({
                    opacity: 0,
                    visibility: "hidden"
                });
            });
        }

        if ($(".tj-text-invert").length && typeof SplitText !== "undefined") {
            var split = new SplitText(".tj-text-invert", { type: "lines" });

            split.lines.forEach(function(target) {
                gsap.to(target, {
                    backgroundPositionX: 0,
                    ease: "none",
                    scrollTrigger: {
                        trigger: target,
                        scrub: 1,
                        start: "top 75%",
                        end: "bottom center"
                    }
                });
            });
        }

        if ($(".title-highlight").length && typeof SplitText !== "undefined") {
            new SplitText(".title-highlight", {
                type: "lines",
                linesClass: "line"
            });

            var tl = gsap.timeline({
                scrollTrigger: {
                    trigger: ".title-highlight",
                    scrub: 1,
                    start: "top 80%",
                    end: "bottom center"
                }
            });

            tl.to(".line", {
                "--highlight-offset": "100%",
                stagger: 0.4
            });
        }
    }

    // portfolio tabs
    $(".h6-project .h6-project-item").on("mouseover", function() {
        $(this).addClass("active").siblings().removeClass("active");
    });

    // Active on hover
    if ($(".tj-hover-active-item").length) {
        var allHoverItems = document.querySelectorAll(".tj-hover-active-item");

        allHoverItems.forEach(function(hoverItem) {
            hoverItem.addEventListener("mouseenter", function() {
                allHoverItems.forEach(function(hoverItem2) {
                    hoverItem2.classList.remove("active");
                });

                this.classList.add("active");
            });
        });
    }

    // Hover bg animation
    function hoverWidget_animation() {
        if (!$(".tj-active-bg-wrapper").length) return;

        var active_bg = $(".tj-active-bg-wrapper .active-bg");
        var element = $(".tj-active-bg-wrapper .current");

        $(".tj-active-bg-wrapper .tj-active-bg-item").on("mouseenter", function() {
            var e = $(this);
            activeHover(active_bg, e);
        });

        $(".tj-active-bg-wrapper").on("mouseleave", function() {
            element = $(".tj-active-bg-wrapper .current");
            activeHover(active_bg, element);
            element.closest(".tj-active-bg-item").siblings().removeClass("mleave");
        });

        activeHover(active_bg, element);

        function activeHover(active_bg, e) {
            if (!e.length || !$(".tj-active-bg-wrapper").length) return false;

            var topOff = e.offset().top;
            var height = e.outerHeight();
            var menuTop = $(".tj-active-bg-wrapper").offset().top;

            e.closest(".tj-active-bg-item").removeClass("mleave");
            e.closest(".tj-active-bg-item").siblings().addClass("mleave");

            active_bg.css({
                top: topOff - menuTop + "px",
                height: height + "px"
            });
        }

        $(".tj-active-bg-wrapper .tj-active-bg-item").on("click", function() {
            $(".tj-active-bg-wrapper .tj-active-bg-item").removeClass("current");
            $(this).addClass("current");
        });
    }

    hoverWidget_animation();

    // image slider
    var tjSliderImages = document.querySelectorAll(".tj-image-slider img");

    if (tjSliderImages.length) {
        var index = 0;

        function showNextImage() {
            tjSliderImages.forEach(function(img) {
                img.classList.remove("active");
            });

            tjSliderImages[index].classList.add("active");
            index = (index + 1) % tjSliderImages.length;
        }

        showNextImage();
        setInterval(showNextImage, 2500);
    }

})(jQuery);