/* ============================================================
   Nebula (星云) — Shared Theme Script
   All behaviors are defensive: each feature no-ops when its
   element is absent. Single namespace: window.Nebula
   ============================================================ */
(function () {
  "use strict";

  var Nebula = {};
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // mark JS as available so CSS can gate the .reveal hidden state on it
  // (layout.php's inline head script adds this before first paint; kept as fallback)
  document.documentElement.classList.add("js");

  /* ------------------------------------------------------------
     1. Starfield canvas (#bg-canvas)
        particles + connecting lines + mouse parallax
     ------------------------------------------------------------ */
  (function starfield() {
    var canvas = document.getElementById("bg-canvas");
    if (!canvas || !canvas.getContext) return;

    var ctx = canvas.getContext("2d");
    var stars = [];
    var W = 0;
    var H = 0;
    var mouse = { x: 0.5, y: 0.5 }; // normalized
    var LINK_DIST = 130;
    var rafId = null;

    function starCount() {
      // scale with area, cap at ~90
      return Math.min(90, Math.round((W * H) / 16000));
    }

    function makeStar() {
      return {
        x: Math.random() * W,
        y: Math.random() * H,
        r: Math.random() * 1.6 + 0.4,
        vx: (Math.random() - 0.5) * 0.25,
        vy: (Math.random() - 0.5) * 0.25,
        depth: Math.random() * 0.8 + 0.2, // parallax factor
        tw: Math.random() * Math.PI * 2   // twinkle phase
      };
    }

    function resize() {
      // back the canvas at device resolution so stars stay crisp on HiDPI,
      // while all drawing code keeps working in CSS-pixel coordinates
      var dpr = window.devicePixelRatio || 1;
      W = window.innerWidth;
      H = window.innerHeight;
      canvas.width = Math.round(W * dpr);
      canvas.height = Math.round(H * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      var n = starCount();
      while (stars.length < n) stars.push(makeStar());
      stars.length = n;
    }

    function isLight() {
      return document.documentElement.getAttribute("data-theme") === "light";
    }

    function draw() {
      // recover if viewport changed without a resize event
      // (e.g. page loaded while hidden/zero-sized)
      if (W !== window.innerWidth || H !== window.innerHeight) resize();

      ctx.clearRect(0, 0, W, H);
      var light = isLight();
      var starColor = light ? "30,36,54" : "232,236,244";
      var px = (mouse.x - 0.5) * 30;
      var py = (mouse.y - 0.5) * 30;
      var i, j, s, o;

      for (i = 0; i < stars.length; i++) {
        s = stars[i];
        s.x += s.vx;
        s.y += s.vy;
        s.tw += 0.02;
        if (s.x < -10) s.x = W + 10; else if (s.x > W + 10) s.x = -10;
        if (s.y < -10) s.y = H + 10; else if (s.y > H + 10) s.y = -10;

        s._dx = s.x + px * s.depth;
        s._dy = s.y + py * s.depth;

        o = 0.35 + 0.45 * Math.abs(Math.sin(s.tw));
        ctx.beginPath();
        ctx.arc(s._dx, s._dy, s.r, 0, Math.PI * 2);
        ctx.fillStyle = "rgba(" + starColor + "," + o.toFixed(2) + ")";
        ctx.fill();
      }

      // connecting lines
      for (i = 0; i < stars.length; i++) {
        for (j = i + 1; j < stars.length; j++) {
          var a = stars[i];
          var b = stars[j];
          var dx = a._dx - b._dx;
          var dy = a._dy - b._dy;
          var d = Math.sqrt(dx * dx + dy * dy);
          if (d < LINK_DIST) {
            var alpha = (1 - d / LINK_DIST) * (light ? 0.10 : 0.14);
            ctx.beginPath();
            ctx.moveTo(a._dx, a._dy);
            ctx.lineTo(b._dx, b._dy);
            ctx.strokeStyle = "rgba(" + starColor + "," + alpha.toFixed(3) + ")";
            ctx.lineWidth = 0.6;
            ctx.stroke();
          }
        }
      }

      rafId = requestAnimationFrame(draw);
    }

    function staticFrame() {
      // static single frame, no animation, no parallax
      var light = isLight();
      var starColor = light ? "30,36,54" : "232,236,244";
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < stars.length; i++) {
        var s = stars[i];
        s._dx = s.x;
        s._dy = s.y;
        ctx.beginPath();
        ctx.arc(s.x, s.y, s.r, 0, Math.PI * 2);
        ctx.fillStyle = "rgba(" + starColor + ",0.6)";
        ctx.fill();
      }
    }

    resize();

    if (reducedMotion) {
      window.addEventListener("resize", function () {
        resize();
        staticFrame();
      });
      staticFrame();
      return;
    }

    window.addEventListener("resize", resize);

    window.addEventListener("mousemove", function (e) {
      mouse.x = e.clientX / W;
      mouse.y = e.clientY / H;
    });

    // pause when tab hidden
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) {
        if (rafId) cancelAnimationFrame(rafId);
        rafId = null;
      } else if (!rafId) {
        rafId = requestAnimationFrame(draw);
      }
    });

    rafId = requestAnimationFrame(draw);
  })();

  /* ------------------------------------------------------------
     2. Theme toggle (#theme-toggle) — persisted in localStorage
     ------------------------------------------------------------ */
  (function themeToggle() {
    var KEY = "nebula-theme";
    var root = document.documentElement;

    // apply saved theme early
    var saved = null;
    try { saved = localStorage.getItem(KEY); } catch (e) { /* private mode */ }
    if (saved === "light" || saved === "dark") {
      root.setAttribute("data-theme", saved);
    }

    var btn = document.getElementById("theme-toggle");
    if (!btn) return;

    btn.addEventListener("click", function () {
      var next = root.getAttribute("data-theme") === "light" ? "dark" : "light";
      root.setAttribute("data-theme", next);
      try { localStorage.setItem(KEY, next); } catch (e) { /* ignore */ }
    });
  })();

  /* ------------------------------------------------------------
     3. Mobile menu (#menu-toggle → body.nav-open, breakpoint 860px)
     ------------------------------------------------------------ */
  (function mobileMenu() {
    var btn = document.getElementById("menu-toggle");
    if (!btn) return;

    btn.addEventListener("click", function () {
      document.body.classList.toggle("nav-open");
    });

    // close when a nav link is clicked
    var nav = document.querySelector(".site-nav");
    if (nav) {
      nav.addEventListener("click", function (e) {
        if (e.target.closest("a")) document.body.classList.remove("nav-open");
      });
    }

    // close when resizing back to desktop
    window.addEventListener("resize", function () {
      if (window.innerWidth > 860) document.body.classList.remove("nav-open");
    });
  })();

  /* ------------------------------------------------------------
     4. Scroll reveal (.reveal → .visible) + skill-bar fills
     ------------------------------------------------------------ */
  (function scrollReveal() {
    var targets = document.querySelectorAll(".reveal, .skill-bar");
    if (!targets.length) return;

    if (!("IntersectionObserver" in window) || reducedMotion) {
      targets.forEach(function (el) { el.classList.add("visible"); });
      return;
    }

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("visible");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });

    targets.forEach(function (el) { io.observe(el); });
  })();

  /* ------------------------------------------------------------
     5. Typing effect ([data-typing] = JSON array of strings)
     ------------------------------------------------------------ */
  (function typing() {
    var els = document.querySelectorAll("[data-typing]");
    if (!els.length) return;

    els.forEach(function (el) {
      var phrases;
      try { phrases = JSON.parse(el.getAttribute("data-typing")); } catch (e) { return; }
      if (!Array.isArray(phrases) || !phrases.length) return;

      // reduced motion: static first phrase, no blinking caret
      if (reducedMotion) {
        el.textContent = phrases[0];
        return;
      }

      // build text span + caret
      var textEl = document.createElement("span");
      var caret = document.createElement("i");
      caret.className = "caret";
      el.textContent = "";
      el.appendChild(textEl);
      el.appendChild(caret);

      var pi = 0;      // phrase index
      var ci = 0;      // char index
      var deleting = false;

      function tick() {
        var phrase = phrases[pi];
        if (!deleting) {
          ci++;
          textEl.textContent = phrase.slice(0, ci);
          if (ci >= phrase.length) {
            deleting = true;
            setTimeout(tick, 1800); // hold full phrase
            return;
          }
          setTimeout(tick, 90 + Math.random() * 60);
        } else {
          ci--;
          textEl.textContent = phrase.slice(0, ci);
          if (ci <= 0) {
            deleting = false;
            pi = (pi + 1) % phrases.length;
            setTimeout(tick, 500);
            return;
          }
          setTimeout(tick, 40);
        }
      }

      setTimeout(tick, 600);
    });
  })();

  /* ------------------------------------------------------------
     6. Back to top (#back-top) — appears after 400px
     ------------------------------------------------------------ */
  (function backTop() {
    var btn = document.getElementById("back-top");
    if (!btn) return;

    var ticking = false;

    function update() {
      btn.classList.toggle("show", window.scrollY > 400);
      ticking = false;
    }

    window.addEventListener("scroll", function () {
      if (!ticking) {
        ticking = true;
        requestAnimationFrame(update);
      }
    }, { passive: true });

    btn.addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: reducedMotion ? "auto" : "smooth" });
    });

    update();
  })();

  /* ------------------------------------------------------------
     7. Active nav fallback — highlight link matching pathname
     ------------------------------------------------------------ */
  (function activeNav() {
    var nav = document.querySelector(".site-nav");
    if (!nav) return;
    if (nav.querySelector("a.active")) return; // page already set it

    var current = location.pathname.split("/").pop() || "index.html";
    var links = nav.querySelectorAll("a[href]");
    links.forEach(function (a) {
      var href = a.getAttribute("href").split("#")[0].split("?")[0];
      if (href === current) a.classList.add("active");
    });
  })();

  window.Nebula = Nebula;
})();
