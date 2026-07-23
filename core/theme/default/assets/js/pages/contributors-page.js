/**
 * 默认主题 · 贡献者页（粒子由 shell.js；本文件仅头像摇摆）
 */
(function () {
    'use strict';

    var avatars = document.querySelectorAll('.contributor-avatar, .avatar-img, .avatar-preview, .link-avatar');
    if (avatars.length === 0) {
        return;
    }

    var style = document.createElement('style');
    style.textContent = [
        '@keyframes swing {',
        '  0% { transform: rotate(0deg); }',
        '  15% { transform: rotate(-20deg); }',
        '  30% { transform: rotate(15deg); }',
        '  45% { transform: rotate(-10deg); }',
        '  60% { transform: rotate(5deg); }',
        '  75% { transform: rotate(-3deg); }',
        '  100% { transform: rotate(0deg); }',
        '}',
        '.avatar-swing { animation: swing 1.2s ease-in-out; transform-origin: center center; }'
    ].join('');
    document.head.appendChild(style);

    function triggerSwing() {
        avatars.forEach(function (avatar) {
            avatar.classList.remove('avatar-swing');
            void avatar.offsetWidth;
            avatar.classList.add('avatar-swing');
        });
    }

    avatars.forEach(function (el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () {
            triggerSwing();
        });
    });

    var MOTION_CONFIG = { shakeThreshold: 8, cooldownPeriod: 500, minShakeCount: 1 };
    var lastShakeTime = 0;
    var shakeCount = 0;
    var lastAcceleration = { x: 0, y: 0, z: 0 };
    var motionListenerAttached = false;

    function getAcc(event) {
        var a = event.accelerationIncludingGravity || event.acceleration;
        if (a && (a.x != null || a.y != null || a.z != null)) {
            return { x: a.x || 0, y: a.y || 0, z: a.z || 0 };
        }
        return null;
    }

    function handleDeviceMotion(event) {
        var acc = getAcc(event);
        if (!acc) {
            return;
        }
        var deltaX = Math.abs(acc.x - lastAcceleration.x);
        var deltaY = Math.abs(acc.y - lastAcceleration.y);
        var deltaZ = Math.abs(acc.z - lastAcceleration.z);
        var delta = Math.sqrt(deltaX * deltaX + deltaY * deltaY + deltaZ * deltaZ);
        var now = Date.now();
        if (delta > MOTION_CONFIG.shakeThreshold) {
            shakeCount += 1;
            if (shakeCount >= MOTION_CONFIG.minShakeCount && now - lastShakeTime > MOTION_CONFIG.cooldownPeriod) {
                triggerSwing();
                lastShakeTime = now;
                shakeCount = 0;
            }
        } else if (shakeCount > 0 && delta < MOTION_CONFIG.shakeThreshold * 0.5) {
            shakeCount = Math.max(0, shakeCount - 1);
        }
        lastAcceleration = { x: acc.x || 0, y: acc.y || 0, z: acc.z || 0 };
    }

    function attachMotionListener() {
        if (motionListenerAttached) {
            return;
        }
        motionListenerAttached = true;
        window.addEventListener('devicemotion', handleDeviceMotion, { passive: true });
    }

    function requestMotionPermission() {
        if (typeof DeviceMotionEvent === 'undefined') {
            return;
        }
        if (typeof DeviceMotionEvent.requestPermission === 'function') {
            DeviceMotionEvent.requestPermission()
                .then(function (permissionState) {
                    if (permissionState === 'granted') {
                        attachMotionListener();
                    }
                })
                .catch(function () { /* ignore */ });
        } else {
            attachMotionListener();
        }
    }

    var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    if (!isMobile) {
        return;
    }

    var permissionTrigger = function () {
        requestMotionPermission();
        document.removeEventListener('click', permissionTrigger);
        document.removeEventListener('touchstart', permissionTrigger);
    };
    document.addEventListener('click', permissionTrigger, { passive: true });
    document.addEventListener('touchstart', permissionTrigger, { passive: true });
    if (typeof DeviceMotionEvent !== 'undefined' && typeof DeviceMotionEvent.requestPermission !== 'function') {
        attachMotionListener();
    }
})();
