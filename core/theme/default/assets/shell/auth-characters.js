/**
 * 登录页角色动画 - 防御性写法，避免 F12 异常暂停
 */
(function () {
    'use strict';

    function initCharacters(options) {
        options = options || {};
        var passwordInputId = options.passwordInputId || 'password';
        var usernameInputId = options.usernameInputId || 'username';

        var $purple = document.getElementById('purple');
        var $black = document.getElementById('black');
        var $orange = document.getElementById('orange');
        var $yellow = document.getElementById('yellow');
        var $purpleEyes = document.getElementById('purple-eyes');
        var $blackEyes = document.getElementById('black-eyes');
        var $orangeEyes = document.getElementById('orange-eyes');
        var $yellowEyes = document.getElementById('yellow-eyes');
        var $yellowMouth = document.getElementById('yellow-mouth');
        var $usernameInput = document.getElementById(usernameInputId);
        var $passwordInput = document.getElementById(passwordInputId);
        var $togglePw = document.getElementById('togglePw');
        var $eyeIcon = document.getElementById('eyeIcon');
        var $eyeOffIcon = document.getElementById('eyeOffIcon');

        if (!$purple || !$black || !$orange || !$yellow) {
            return;
        }

        var mouseX = 0;
        var mouseY = 0;
        var isTyping = false;
        var showPassword = false;
        var passwordLen = 0;
        var purpleBlink = false;
        var blackBlink = false;
        var lookingAtEachOther = false;
        var purplePeeking = false;

        document.addEventListener('mousemove', function (e) {
            mouseX = e.clientX;
            mouseY = e.clientY;
        });

        if ($usernameInput) {
            $usernameInput.addEventListener('focus', function () {
                isTyping = true;
            });
            $usernameInput.addEventListener('blur', function () {
                isTyping = false;
            });
        }

        if ($passwordInput) {
            $passwordInput.addEventListener('input', function () {
                passwordLen = $passwordInput.value.length;
            });
            $passwordInput.addEventListener('focus', function () {
                isTyping = true;
                triggerLookAtEachOther();
            });
            $passwordInput.addEventListener('blur', function () {
                isTyping = false;
            });
        }

        if ($togglePw && $passwordInput) {
            $togglePw.addEventListener('click', function () {
                showPassword = !showPassword;
                $passwordInput.type = showPassword ? 'text' : 'password';
                if ($eyeIcon) {
                    $eyeIcon.style.display = showPassword ? 'none' : '';
                }
                if ($eyeOffIcon) {
                    $eyeOffIcon.style.display = showPassword ? '' : 'none';
                }
            });
        }

        function triggerLookAtEachOther() {
            lookingAtEachOther = true;
            setTimeout(function () {
                lookingAtEachOther = false;
            }, 800);
        }

        function scheduleBlink(setter) {
            var delay = Math.random() * 4000 + 3000;
            setTimeout(function () {
                setter(true);
                setTimeout(function () {
                    setter(false);
                    scheduleBlink(setter);
                }, 150);
            }, delay);
        }

        scheduleBlink(function (v) {
            purpleBlink = v;
        });
        scheduleBlink(function (v) {
            blackBlink = v;
        });

        function schedulePeek() {
            if (passwordLen > 0 && showPassword) {
                var delay = Math.random() * 3000 + 2000;
                setTimeout(function () {
                    if (passwordLen > 0 && showPassword) {
                        purplePeeking = true;
                        setTimeout(function () {
                            purplePeeking = false;
                            schedulePeek();
                        }, 800);
                    }
                }, delay);
            }
        }

        setInterval(function () {
            if (passwordLen > 0 && showPassword && !purplePeeking) {
                schedulePeek();
            }
        }, 1000);

        function calcPos(el) {
            var rect = el.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 3;
            var dx = mouseX - cx;
            var dy = mouseY - cy;
            return {
                faceX: Math.max(-15, Math.min(15, dx / 20)),
                faceY: Math.max(-10, Math.min(10, dy / 30)),
                bodySkew: Math.max(-6, Math.min(6, -dx / 120))
            };
        }

        function eyePupilOffset(el, maxDist, forceX, forceY) {
            if (forceX !== undefined && forceY !== undefined) {
                return { x: forceX, y: forceY };
            }
            var rect = el.getBoundingClientRect();
            var cx = rect.left + rect.width / 2;
            var cy = rect.top + rect.height / 2;
            var dx = mouseX - cx;
            var dy = mouseY - cy;
            var dist = Math.min(Math.sqrt(dx * dx + dy * dy), maxDist);
            var angle = Math.atan2(dy, dx);
            return { x: Math.cos(angle) * dist, y: Math.sin(angle) * dist };
        }

        function setPupil(eyeEl, maxDist, forceX, forceY) {
            if (!eyeEl) return;
            var pupil = eyeEl.querySelector('.pupil');
            if (!pupil) return;
            var o = eyePupilOffset(eyeEl, maxDist, forceX, forceY);
            pupil.style.transform = 'translate(' + o.x + 'px, ' + o.y + 'px)';
        }

        function setPupilOnly(el, maxDist, forceX, forceY) {
            if (!el) return;
            var o = eyePupilOffset(el, maxDist, forceX, forceY);
            el.style.transform = 'translate(' + o.x + 'px, ' + o.y + 'px)';
        }

        function render() {
            var pp = calcPos($purple);
            var bp = calcPos($black);
            var op = calcPos($orange);
            var yp = calcPos($yellow);
            var isHiding = passwordLen > 0 && !showPassword;
            var isShowingPw = passwordLen > 0 && showPassword;

            if (isShowingPw) {
                $purple.style.transform = 'skewX(0deg)';
                $purple.style.height = '400px';
            } else if (isTyping || isHiding) {
                $purple.style.transform = 'skewX(' + ((pp.bodySkew || 0) - 12) + 'deg) translateX(40px)';
                $purple.style.height = '440px';
            } else {
                $purple.style.transform = 'skewX(' + (pp.bodySkew || 0) + 'deg)';
                $purple.style.height = '400px';
            }

            if ($purpleEyes) {
                var purpleEyeL = $purpleEyes.children[0];
                var purpleEyeR = $purpleEyes.children[1];
                if (purpleEyeL) purpleEyeL.style.height = purpleBlink ? '2px' : '18px';
                if (purpleEyeR) purpleEyeR.style.height = purpleBlink ? '2px' : '18px';

                var pfx;
                var pfy;
                if (isShowingPw) {
                    $purpleEyes.style.left = '20px';
                    $purpleEyes.style.top = '35px';
                    pfx = purplePeeking ? 4 : -4;
                    pfy = purplePeeking ? 5 : -4;
                } else if (lookingAtEachOther) {
                    $purpleEyes.style.left = '55px';
                    $purpleEyes.style.top = '65px';
                    pfx = 3;
                    pfy = 4;
                } else {
                    $purpleEyes.style.left = (45 + pp.faceX) + 'px';
                    $purpleEyes.style.top = (40 + pp.faceY) + 'px';
                    pfx = undefined;
                    pfy = undefined;
                }
                setPupil(purpleEyeL, 5, pfx, pfy);
                setPupil(purpleEyeR, 5, pfx, pfy);
            }

            if (isShowingPw) {
                $black.style.transform = 'skewX(0deg)';
            } else if (lookingAtEachOther) {
                $black.style.transform = 'skewX(' + ((bp.bodySkew || 0) * 1.5 + 10) + 'deg) translateX(20px)';
            } else if (isTyping || isHiding) {
                $black.style.transform = 'skewX(' + ((bp.bodySkew || 0) * 1.5) + 'deg)';
            } else {
                $black.style.transform = 'skewX(' + (bp.bodySkew || 0) + 'deg)';
            }

            if ($blackEyes) {
                var blackEyeL = $blackEyes.children[0];
                var blackEyeR = $blackEyes.children[1];
                if (blackEyeL) blackEyeL.style.height = blackBlink ? '2px' : '16px';
                if (blackEyeR) blackEyeR.style.height = blackBlink ? '2px' : '16px';

                var bfx;
                var bfy;
                if (isShowingPw) {
                    $blackEyes.style.left = '10px';
                    $blackEyes.style.top = '28px';
                    bfx = -4;
                    bfy = -4;
                } else if (lookingAtEachOther) {
                    $blackEyes.style.left = '32px';
                    $blackEyes.style.top = '12px';
                    bfx = 0;
                    bfy = -4;
                } else {
                    $blackEyes.style.left = (26 + bp.faceX) + 'px';
                    $blackEyes.style.top = (32 + bp.faceY) + 'px';
                    bfx = undefined;
                    bfy = undefined;
                }
                setPupil(blackEyeL, 4, bfx, bfy);
                setPupil(blackEyeR, 4, bfx, bfy);
            }

            $orange.style.transform = isShowingPw ? 'skewX(0deg)' : 'skewX(' + (op.bodySkew || 0) + 'deg)';

            if ($orangeEyes) {
                var ofx;
                var ofy;
                if (isShowingPw) {
                    $orangeEyes.style.left = '50px';
                    $orangeEyes.style.top = '85px';
                    ofx = -5;
                    ofy = -4;
                } else {
                    $orangeEyes.style.left = (82 + (op.faceX || 0)) + 'px';
                    $orangeEyes.style.top = (90 + (op.faceY || 0)) + 'px';
                    ofx = undefined;
                    ofy = undefined;
                }
                setPupilOnly($orangeEyes.children[0], 5, ofx, ofy);
                setPupilOnly($orangeEyes.children[1], 5, ofx, ofy);
            }

            $yellow.style.transform = isShowingPw ? 'skewX(0deg)' : 'skewX(' + (yp.bodySkew || 0) + 'deg)';

            if ($yellowEyes) {
                var yfx;
                var yfy;
                if (isShowingPw) {
                    $yellowEyes.style.left = '20px';
                    $yellowEyes.style.top = '35px';
                    if ($yellowMouth) {
                        $yellowMouth.style.left = '10px';
                        $yellowMouth.style.top = '88px';
                    }
                    yfx = -5;
                    yfy = -4;
                } else {
                    $yellowEyes.style.left = (52 + (yp.faceX || 0)) + 'px';
                    $yellowEyes.style.top = (40 + (yp.faceY || 0)) + 'px';
                    if ($yellowMouth) {
                        $yellowMouth.style.left = (40 + (yp.faceX || 0)) + 'px';
                        $yellowMouth.style.top = (88 + (yp.faceY || 0)) + 'px';
                    }
                    yfx = undefined;
                    yfy = undefined;
                }
                setPupilOnly($yellowEyes.children[0], 5, yfx, yfy);
                setPupilOnly($yellowEyes.children[1], 5, yfx, yfy);
            }

            requestAnimationFrame(render);
        }

        requestAnimationFrame(render);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initCharacters(window.CHARACTER_OPTIONS || {});
        });
    } else {
        initCharacters(window.CHARACTER_OPTIONS || {});
    }
})();
