function sblogText(key, fallback, variables = {}) {
  const messages = window.sblogI18n;
  const text = typeof messages?.[key] === "string" ? messages[key] : fallback;
  return text.replace(/\{([A-Za-z_][A-Za-z0-9_.-]*)\}/g, (placeholder, name) => (
    Object.prototype.hasOwnProperty.call(variables, name) ? String(variables[name]) : placeholder
  ));
}

function initComments() {
  document.querySelectorAll(".comments").forEach((root) => {
    const form = root.querySelector(".comment-form");
    if (!form) {
      root.addEventListener("click", (event) => event.stopPropagation());
      return;
    }

    const parentInput = form.querySelector("[data-comment-parent-id]");
    const replyState = form.querySelector("[data-comment-reply-state]");
    const replyName = form.querySelector("[data-comment-reply-name]");
    const cancelButton = form.querySelector("[data-comment-reply-cancel]");
    const content = form.querySelector("#comment-content");
    const replyButtons = [...root.querySelectorAll("[data-comment-reply]")];
    let activeReplyButton = null;

    const setReply = (button, focusContent = true) => {
      const commentId = button.dataset.commentId || "";
      const author = button.dataset.commentAuthor || "";
      if (!parentInput || !replyState || !replyName || !content || !/^\d+$/.test(commentId)) return;

      parentInput.value = commentId;
      replyState.hidden = false;
      activeReplyButton = button;
      replyButtons.forEach((item) => {
        item.setAttribute("aria-pressed", item === button ? "true" : "false");
      });
      cancelButton?.setAttribute("aria-label", sblogText("cancel_reply_to", "取消回复 @{author}", { author }));

      if (focusContent) {
        replyName.textContent = "";
        requestAnimationFrame(() => {
          if (parentInput.value !== commentId) return;
          replyName.textContent = `@${author}`;
          form.scrollIntoView({
            behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth",
            block: "start",
          });
          requestAnimationFrame(() => {
            if (parentInput.value === commentId) {
              content.focus({ preventScroll: true });
            }
          });
        });
      } else {
        replyName.textContent = `@${author}`;
      }
    };

    const clearReply = () => {
      if (!parentInput || !replyState || !replyName) return;
      const returnTarget = activeReplyButton;
      parentInput.value = "";
      replyName.textContent = "";
      replyState.hidden = true;
      activeReplyButton = null;
      replyButtons.forEach((item) => item.setAttribute("aria-pressed", "false"));
      cancelButton?.setAttribute("aria-label", sblogText("cancel_reply", "取消回复"));
      (returnTarget || content)?.focus();
    };

    root.addEventListener("click", (event) => {
      event.stopPropagation();
      if (!(event.target instanceof Element)) return;

      const replyButton = event.target.closest("[data-comment-reply]");
      if (replyButton && root.contains(replyButton)) {
        setReply(replyButton);
        return;
      }

      if (event.target.closest("[data-comment-reply-cancel]")) clearReply();
    });

    const initialReply = replyButtons.find((button) => button.dataset.commentId === parentInput?.value);
    if (initialReply) setReply(initialReply, false);
  });
}

function initTerminal() {
  const term = document.querySelector(".terminal");
  const output = document.querySelector("#output");
  const input = document.querySelector("#input");
  const shown = document.querySelector("#input-text");
  const ghost = document.querySelector("#ghost-text");
  const scan = document.querySelector("#scanlines");
  if (!term || !output || !input || !shown || !ghost || !scan) return;

  const history = [];
  let historyIndex = 0;
  const routes = {
    home: term.dataset.home,
    tags: term.dataset.tags,
    links: term.dataset.links,
    archives: term.dataset.archives,
  };
  const commands = [
    "help",
    "ls",
    "cat",
    "cd",
    "pwd",
    "clear",
    "history",
    "theme",
    "crt",
    "date",
    "home",
    "tags",
    "links",
    "archives",
  ];

  const print = (text, className = "") => {
    const line = document.createElement("div");
    line.className = `line ${className}`;
    line.textContent = text;
    output.append(line);
    output.scrollTop = output.scrollHeight;
  };

  const syncInput = () => {
    shown.textContent = input.value;
    const hit = commands.find((command) => command.startsWith(input.value) && command !== input.value);
    ghost.textContent = input.value && hit ? hit.slice(input.value.length) : "";
  };

  const switchTheme = (name) => {
    const themes = {
      phosphor: ["#7ec699", "#a8e8a8"],
      amber: ["#e8a87c", "#ffb86c"],
      cyan: ["#7aa6da", "#a8d0f0"],
    };
    const theme = themes[name];
    if (!theme) {
      print(sblogText("terminal_themes_available", "themes: phosphor, amber, cyan"), "dim");
      return;
    }

    document.documentElement.style.setProperty("--green", theme[0]);
    document.documentElement.style.setProperty("--bright", theme[1]);
    print(sblogText("terminal_theme_switched", "theme: switched to {name}", { name }), "green");
  };

  const runCommand = (raw) => {
    const value = raw.trim();
    const [command, argument] = value.split(/\s+/, 2);
    print(`visitor@devlog:~$ ${value}`, "cmd-echo");

    if (!value) return;
    if (routes[command]) {
      location.href = routes[command];
      return;
    }
    if (command === "clear") {
      output.innerHTML = "";
      return;
    }
    if (command === "pwd") {
      print("~", "green");
      return;
    }
    if (command === "date") {
      print(new Date().toLocaleString(document.documentElement.lang || undefined), "green");
      return;
    }
    if (command === "crt") {
      scan.classList.toggle("disabled");
      print(scan.classList.contains("disabled")
        ? sblogText("terminal_crt_scanlines_disabled", "CRT scanlines: disabled")
        : sblogText("terminal_crt_scanlines_enabled", "CRT scanlines: enabled"), "dim");
      return;
    }
    if (command === "history") {
      history.forEach((item, index) => print(`${String(index + 1).padStart(4)}  ${item}`, "dim"));
      return;
    }
    if (command === "theme") {
      switchTheme(argument);
      return;
    }
    if (command === "ls") {
      print("home/  tags/  links/  archives/  rss.xml", "green");
      document.querySelectorAll(".posts .post a").forEach((link) => print(`${link.textContent}.md`, "blue"));
      return;
    }
    if (command === "cd" || command === "cat") {
      print(command === "cd"
        ? sblogText("terminal_use_cd", "Use: cd tags")
        : sblogText("terminal_use_cat", "Use: open an article link from ls"), "dim");
      return;
    }
    if (command === "help") {
      print(sblogText("terminal_commands_heading", "COMMANDS"), "amber");
      print(sblogText("terminal_help_ls", "  ls                         list posts and sections"));
      print(sblogText("terminal_help_navigation", "  home|tags|links|archives   navigate site"));
      print(sblogText("terminal_help_utilities", "  clear|history|pwd          shell utilities"));
      print(sblogText("terminal_help_theme", "  theme <name>               phosphor, amber, cyan"));
      print(sblogText("terminal_help_display", "  crt|date                    display controls"));
      return;
    }

    print(sblogText("terminal_command_not_found", '{command}: command not found. Type "help".', { command }), "red");
  };

  input.addEventListener("input", syncInput);
  input.addEventListener("keydown", (event) => {
    if (event.key === "Enter") {
      event.preventDefault();
      if (input.value.trim()) {
        history.push(input.value.trim());
        historyIndex = history.length;
      }
      runCommand(input.value);
      input.value = "";
      syncInput();
    } else if (event.key === "Tab" && ghost.textContent) {
      event.preventDefault();
      input.value += ghost.textContent;
      syncInput();
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      if (historyIndex > 0) input.value = history[--historyIndex] || "";
      syncInput();
    } else if (event.key === "ArrowDown") {
      event.preventDefault();
      input.value =
        historyIndex < history.length - 1 ? history[++historyIndex] : ((historyIndex = history.length), "");
      syncInput();
    } else if (event.ctrlKey && event.key.toLowerCase() === "l") {
      event.preventDefault();
      output.innerHTML = "";
    }
  });

  document.addEventListener("click", () => input.focus());

  const updateSize = () => {
    const info = document.querySelector("#term-info");
    if (info) {
      info.textContent = `${Math.floor(output.clientWidth / 8)}×${Math.floor(output.clientHeight / 16)}`;
    }
  };

  addEventListener("resize", updateSize);
  updateSize();
  setTimeout(() => document.querySelector("#turn-on")?.remove(), 800);
  input.focus();
}
document.addEventListener("DOMContentLoaded", () => {
  initComments();
  initTerminal();
});
