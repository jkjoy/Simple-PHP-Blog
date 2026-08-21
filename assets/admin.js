function sblogText(key, fallback, variables = {}) {
  const messages = window.sblogI18n;
  const text = typeof messages?.[key] === "string" ? messages[key] : fallback;
  return text.replace(/\{([A-Za-z_][A-Za-z0-9_.-]*)\}/g, (placeholder, name) => (
    Object.prototype.hasOwnProperty.call(variables, name) ? String(variables[name]) : placeholder
  ));
}

function initAdminTheme() {
  const controls = Array.from(document.querySelectorAll("[data-admin-theme-toggle]"));
  if (!controls.length) return;

  const root = document.documentElement;
  const systemTheme = window.matchMedia("(prefers-color-scheme: dark)");
  const savedTheme = () => {
    try {
      const value = localStorage.getItem("sblog-admin-theme");
      return value === "light" || value === "dark" ? value : "";
    } catch (error) {
      return "";
    }
  };
  const applyTheme = (theme, persist = false) => {
    root.dataset.adminTheme = theme;
    controls.forEach((control) => {
      const nextLabel = theme === "dark"
        ? sblogText("switch_to_light", "切换到浅色模式")
        : sblogText("switch_to_dark", "切换到深色模式");
      control.setAttribute("aria-label", nextLabel);
      control.setAttribute("aria-pressed", theme === "dark" ? "true" : "false");
      control.setAttribute("title", nextLabel);
    });
    if (persist) {
      try {
        localStorage.setItem("sblog-admin-theme", theme);
      } catch (error) {
        // The active page still keeps the selected theme when storage is unavailable.
      }
    }
  };

  applyTheme(root.dataset.adminTheme === "dark" ? "dark" : "light");
  controls.forEach((control) => {
    control.addEventListener("click", () => {
      applyTheme(root.dataset.adminTheme === "dark" ? "light" : "dark", true);
    });
  });

  const syncSystemTheme = (event) => {
    if (!savedTheme()) applyTheme(event.matches ? "dark" : "light");
  };
  if (typeof systemTheme.addEventListener === "function") {
    systemTheme.addEventListener("change", syncSystemTheme);
  } else {
    systemTheme.addListener(syncSystemTheme);
  }
}

function initThemeManager() {
  const manager = document.querySelector("[data-theme-manager]");
  if (!(manager instanceof HTMLElement) || typeof window.fetch !== "function") return;

  const cards = Array.from(manager.querySelectorAll("[data-theme-card]"));
  const forms = Array.from(manager.querySelectorAll("[data-theme-activate]"));
  if (!cards.length || !forms.length) return;

  const setActiveTheme = (slug) => {
    cards.forEach((card) => {
      if (!(card instanceof HTMLElement)) return;
      const isActive = card.dataset.themeSlug === slug;
      card.classList.toggle("is-active", isActive);
      card.querySelector("[data-theme-current]")?.toggleAttribute("hidden", !isActive);
      card.querySelector("[data-theme-activate]")?.toggleAttribute("hidden", isActive);
      card.querySelector("[data-theme-active]")?.toggleAttribute("hidden", !isActive);
    });
  };

  forms.forEach((form) => {
    if (!(form instanceof HTMLFormElement)) return;
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (manager.getAttribute("aria-busy") === "true") return;

      const card = form.closest("[data-theme-card]");
      const slug = card instanceof HTMLElement ? card.dataset.themeSlug || "" : "";
      if (!slug) return;

      const previousCard = cards.find((item) => item.classList.contains("is-active"));
      const previousSlug = previousCard instanceof HTMLElement ? previousCard.dataset.themeSlug || "" : "";
      manager.setAttribute("aria-busy", "true");
      forms.forEach((item) => {
        const button = item.querySelector('button[type="submit"]');
        if (button instanceof HTMLButtonElement) button.disabled = true;
      });
      setActiveTheme(slug);

      try {
        const response = await fetch(form.action, {
          method: "POST",
          body: new FormData(form),
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
          },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const payload = await response.json();
        if (payload?.ok !== true || payload.active_theme !== slug) {
          throw new Error("Invalid theme activation response");
        }
      } catch (error) {
        setActiveTheme(previousSlug);
        window.alert(sblogText("theme_activate_failed", "主题切换失败，请重试。"));
      } finally {
        manager.removeAttribute("aria-busy");
        forms.forEach((item) => {
          const button = item.querySelector('button[type="submit"]');
          if (button instanceof HTMLButtonElement) button.disabled = false;
        });
      }
    });
  });
}

function initPasswordToggles() {
  document.querySelectorAll("[data-password-toggle]").forEach((control) => {
    if (!(control instanceof HTMLButtonElement)) return;
    const input = document.getElementById(control.dataset.passwordToggle || "");
    if (!(input instanceof HTMLInputElement)) return;

    control.addEventListener("click", () => {
      const reveal = input.type === "password";
      input.type = reveal ? "text" : "password";
      const label = reveal
        ? sblogText("hide_password", "隐藏密码")
        : sblogText("show_password", "显示密码");
      control.setAttribute("aria-label", label);
      control.setAttribute("aria-pressed", reveal ? "true" : "false");
      control.setAttribute("title", label);
      input.focus({ preventScroll: true });
    });
  });
}

function initAccountMenus() {
  const accountMenus = Array.from(document.querySelectorAll("[data-admin-account]"));
  if (!accountMenus.length) return;

  accountMenus.forEach((menu) => {
    menu.addEventListener("toggle", () => {
      if (!menu.open) return;
      accountMenus.forEach((otherMenu) => {
        if (otherMenu !== menu) {
          otherMenu.open = false;
        }
      });
    });
  });

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Node)) return;
    accountMenus.forEach((menu) => {
      if (!menu.contains(target)) {
        menu.open = false;
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    accountMenus.forEach((menu) => {
      if (!menu.open) return;
      menu.open = false;
      const toggle = menu.querySelector("summary");
      if (toggle instanceof HTMLElement) {
        toggle.focus();
      }
    });
  });
}

function initAdminNavigation() {
  const body = document.body;
  const sidebar = document.querySelector("#admin-sidebar");
  const main = document.querySelector(".admin-main");
  const toggle = document.querySelector("[data-admin-nav-toggle]");
  const closeControls = document.querySelectorAll("[data-admin-nav-close]");
  const mobile = window.matchMedia("(max-width: 760px)");
  if (!(sidebar instanceof HTMLElement) || !(toggle instanceof HTMLButtonElement)) return;

  const setOpen = (open, restoreFocus = false) => {
    const mobileOpen = mobile.matches && open;
    body.classList.toggle("admin-nav-open", mobileOpen);
    toggle.setAttribute("aria-expanded", mobileOpen ? "true" : "false");
    toggle.setAttribute("aria-label", mobileOpen
      ? sblogText("close_admin_menu", "关闭后台菜单")
      : sblogText("open_admin_menu", "打开后台菜单"));
    sidebar.toggleAttribute("inert", mobile.matches && !mobileOpen);
    if (mobile.matches) {
      sidebar.setAttribute("aria-hidden", mobileOpen ? "false" : "true");
    } else {
      sidebar.removeAttribute("aria-hidden");
    }
    if (main instanceof HTMLElement) main.toggleAttribute("inert", mobileOpen);

    if (mobileOpen) {
      const closeButton = sidebar.querySelector("[data-admin-nav-close]");
      if (closeButton instanceof HTMLElement) {
        window.requestAnimationFrame(() => {
          window.requestAnimationFrame(() => closeButton.focus({ preventScroll: true }));
        });
      }
    } else {
      sidebar.querySelectorAll("[data-admin-account]").forEach((menu) => { menu.open = false; });
      if (restoreFocus) toggle.focus();
    }
  };

  toggle.addEventListener("click", () => setOpen(!body.classList.contains("admin-nav-open"), true));
  closeControls.forEach((control) => control.addEventListener("click", () => setOpen(false, true)));
  sidebar.querySelectorAll("a").forEach((link) => link.addEventListener("click", () => setOpen(false)));
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && body.classList.contains("admin-nav-open")) setOpen(false, true);
  });
  const handleViewportChange = () => setOpen(false);
  if (typeof mobile.addEventListener === "function") {
    mobile.addEventListener("change", handleViewportChange);
  } else {
    mobile.addListener(handleViewportChange);
  }
  setOpen(false);
}

function initSettingsControls() {
  const prettyUrl = document.getElementById("pretty_url");
  const rewriteHelp = document.querySelector("[data-rewrite-help]");
  if (prettyUrl && rewriteHelp) {
    const toggleRewriteHelp = () => {
      rewriteHelp.hidden = prettyUrl.value !== "1";
    };
    prettyUrl.addEventListener("change", toggleRewriteHelp);
    toggleRewriteHelp();
  }

  document.querySelectorAll("[data-check-all]").forEach((control) => {
    control.addEventListener("change", () => {
      const name = control.dataset.checkAll;
      document.querySelectorAll(`input[name="${name}"]`).forEach((checkbox) => {
        checkbox.checked = control.checked;
      });
    });
  });
}

function initTagManager() {
  const manager = document.querySelector("[data-tag-manager]");
  if (!(manager instanceof HTMLElement)) return;

  const items = Array.from(manager.querySelectorAll("[data-tag-item]"));
  const editForm = manager.querySelector("[data-tag-edit-form]");
  const editor = manager.querySelector("[data-tag-editor]");
  const oldInput = editForm?.querySelector("[data-tag-old]");
  const nameInput = editForm?.querySelector("[data-tag-name]");
  const slugInput = editForm?.querySelector("[data-tag-slug]");
  const saveButton = editForm?.querySelector("[data-tag-save]");
  const deleteButton = manager.querySelector("[data-tag-delete]");
  const selectAllButton = manager.querySelector("[data-tag-check-all]");
  const selectionCount = manager.querySelector("[data-tag-selection-count]");
  const editorStatus = manager.querySelector("[data-tag-editor-status]");
  if (!(editForm instanceof HTMLFormElement) || !items.length) return;

  const controls = [oldInput, nameInput, slugInput].filter((control) => control instanceof HTMLInputElement);
  const selectedItems = () => items.filter((item) => {
    const input = item.querySelector("[data-tag-input]");
    return input instanceof HTMLInputElement && !input.disabled;
  });

  const setItemSelected = (item, selected) => {
    const input = item.querySelector("[data-tag-input]");
    const button = item.querySelector("[data-tag-select]");
    if (input instanceof HTMLInputElement) input.disabled = !selected;
    if (button instanceof HTMLButtonElement) button.setAttribute("aria-pressed", selected ? "true" : "false");
    item.classList.toggle("is-marked", selected);
  };

  const setEditorEnabled = (enabled) => {
    controls.forEach((control) => { control.disabled = !enabled; });
    if (saveButton instanceof HTMLButtonElement) saveButton.disabled = !enabled;
  };

  const replaceUrl = (url) => {
    if (typeof window.history?.replaceState !== "function") return;
    window.history.replaceState(null, "", url);
  };

  const activateItem = (item, updateUrl = true, revealEditor = false) => {
    if (!(item instanceof HTMLElement)) return;
    const label = item.dataset.tagLabel || "";
    const slug = item.dataset.tagSlug || "";
    if (!label || !(oldInput instanceof HTMLInputElement) || !(nameInput instanceof HTMLInputElement) || !(slugInput instanceof HTMLInputElement)) return;

    items.forEach((candidate) => {
      const active = candidate === item;
      candidate.classList.toggle("is-active", active);
    });
    oldInput.value = label;
    nameInput.value = label;
    slugInput.value = slug;
    setEditorEnabled(true);
    if (editorStatus instanceof HTMLElement) editorStatus.textContent = `#${label}`;

    const button = item.querySelector("[data-tag-select]");
    if (updateUrl && button instanceof HTMLButtonElement && button.dataset.tagUrl) replaceUrl(button.dataset.tagUrl);
    if (!revealEditor) return;
    if (window.matchMedia("(max-width: 760px)").matches && editor instanceof HTMLElement) {
      const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
      editor.scrollIntoView({ behavior: reducedMotion ? "auto" : "smooth", block: "start" });
    } else {
      nameInput.focus({ preventScroll: true });
      nameInput.select();
    }
  };

  const clearEditor = () => {
    items.forEach((item) => item.classList.remove("is-active"));
    controls.forEach((control) => { control.value = ""; });
    setEditorEnabled(false);
    if (editorStatus instanceof HTMLElement) editorStatus.textContent = sblogText("no_tag_selected", "未选择标签");
    replaceUrl(manager.dataset.baseUrl || window.location.pathname);
  };

  const updateSelectionState = () => {
    const count = selectedItems().length;
    if (selectAllButton instanceof HTMLButtonElement) {
      const allSelected = count === items.length;
      selectAllButton.setAttribute("aria-pressed", allSelected ? "true" : "false");
      selectAllButton.textContent = allSelected
        ? sblogText("clear_selection", "取消全选")
        : sblogText("select_all", "全选");
    }
    if (deleteButton instanceof HTMLButtonElement) deleteButton.disabled = count === 0;
    if (selectionCount instanceof HTMLElement) {
      selectionCount.textContent = count > 0
        ? sblogText("selected_tag_count", "已选择 {count} 个标签", { count })
        : sblogText("no_tag_selected", "未选择标签");
    }
  };

  items.forEach((item) => {
    const button = item.querySelector("[data-tag-select]");
    button?.addEventListener("click", () => {
      const input = item.querySelector("[data-tag-input]");
      if (!(input instanceof HTMLInputElement)) return;
      if (input.disabled) {
        setItemSelected(item, true);
        activateItem(item, true, true);
      } else {
        setItemSelected(item, false);
        if (!item.classList.contains("is-active")) {
          updateSelectionState();
          return;
        }
        const next = selectedItems()[0];
        if (next) activateItem(next);
        else clearEditor();
      }
      updateSelectionState();
    });
  });

  selectAllButton?.addEventListener("click", () => {
    const shouldSelect = selectedItems().length !== items.length;
    items.forEach((item) => setItemSelected(item, shouldSelect));
    if (!shouldSelect) clearEditor();
    updateSelectionState();
  });

  updateSelectionState();
}

function initAttachmentUploader() {
  const uploader = document.querySelector(".attachment-uploader");
  if (!uploader) return;

  const input = uploader.querySelector(".attachment-input");
  const drop = uploader.querySelector(".attachment-drop");
  const list = uploader.querySelector(".attachment-list");
  const editor = document.getElementById("content");
  const uploadUrl = uploader.dataset.uploadUrl || "";
  const csrf = uploader.dataset.csrf || "";
  const refreshOnUpload = uploader.dataset.refreshOnUpload === "1";
  const maxSize = 30 * 1024 * 1024;

  if (!input || !drop || !list || !uploadUrl || !csrf) return;

  const pastedImageExtension = (type) => {
    const normalized = String(type || "").toLowerCase();
    if (normalized === "image/jpeg") return "jpg";
    if (normalized === "image/gif") return "gif";
    if (normalized === "image/webp") return "webp";
    return "png";
  };

  const normalizePastedImage = (file, index) => {
    if (!(file instanceof File) || !file.type.startsWith("image/")) return null;
    const name = file.name && /\.[a-z0-9]{2,5}$/i.test(file.name)
      ? file.name
      : `pasted-image-${Date.now()}-${index + 1}.${pastedImageExtension(file.type)}`;
    if (name === file.name) return file;
    try {
      return new File([file], name, { type: file.type, lastModified: file.lastModified });
    } catch (error) {
      return file;
    }
  };

  const pastedImages = (event) => {
    const clipboard = event.clipboardData;
    if (!clipboard) return [];

    const files = [];
    if (clipboard.items) {
      Array.from(clipboard.items).forEach((item) => {
        if (item.kind !== "file" || !item.type.startsWith("image/")) return;
        const file = item.getAsFile();
        if (file) files.push(file);
      });
    }
    if (!files.length && clipboard.files) {
      Array.from(clipboard.files).forEach((file) => {
        if (file.type.startsWith("image/")) files.push(file);
      });
    }
    return files.map(normalizePastedImage).filter(Boolean);
  };

  const appendMarkdown = (markdown) => {
    if (!editor) return;
    const start = editor.selectionStart ?? editor.value.length;
    const end = editor.selectionEnd ?? editor.value.length;
    const before = editor.value.slice(0, start);
    const after = editor.value.slice(end);
    const prefix = before === "" || before.endsWith("\n") ? "" : "\n";
    const insert = `${prefix}${markdown}\n`;

    editor.value = before + insert + after;
    const cursor = before.length + insert.length;
    editor.focus();
    editor.setSelectionRange(cursor, cursor);
    editor.dispatchEvent(new Event("input", { bubbles: true }));
  };

  const createItem = (file) => {
    const item = document.createElement("div");
    item.className = "attachment-item";

    const preview = document.createElement("div");
    preview.className = "attachment-preview";
    if (file.type.startsWith("image/")) {
      const image = document.createElement("img");
      image.alt = file.name;
      image.src = URL.createObjectURL(file);
      image.onload = () => URL.revokeObjectURL(image.src);
      preview.appendChild(image);
    } else {
      preview.textContent = sblogText("file_preview_label", "FILE");
    }

    const body = document.createElement("div");
    body.className = "attachment-item__body";

    const name = document.createElement("strong");
    name.textContent = file.name;

    const status = document.createElement("span");
    status.textContent = file.size > maxSize
      ? sblogText("file_too_large", "文件超过 30M")
      : sblogText("waiting_to_upload", "等待上传");

    body.append(name, status);
    item.append(preview, body);
    list.appendChild(item);

    return { item, status };
  };

  const uploadFiles = async (files) => {
    const selected = Array.from(files || []);

    for (const file of selected) {
      const row = createItem(file);
      if (file.size > maxSize) {
        row.item.classList.add("is-error");
        continue;
      }

      row.status.textContent = sblogText("uploading", "上传中...");

      const data = new FormData();
      data.append("csrf_token", csrf);
      data.append("attachments[]", file);

      try {
        const response = await fetch(uploadUrl, {
          method: "POST",
          body: data,
          credentials: "same-origin",
        });
        const result = await response.json();

        if (!response.ok || !result.ok || !result.files?.length) {
          const message = result.errors?.[0]?.error || result.error || sblogText("upload_failed", "上传失败");
          throw new Error(message);
        }

        const uploaded = result.files[0];
        row.item.classList.add("is-done");
        row.status.textContent = editor
          ? sblogText("uploaded_and_inserted", "已上传并插入 Markdown")
          : sblogText("upload_complete", "上传完成");
        appendMarkdown(uploaded.markdown);

        if (uploaded.is_image) {
          const image = row.item.querySelector(".attachment-preview img");
          if (image) {
            image.src = uploaded.url;
          }
        }
      } catch (error) {
        row.item.classList.add("is-error");
        row.status.textContent = error instanceof Error ? error.message : sblogText("upload_failed", "上传失败");
      }
    }

    input.value = "";
    if (refreshOnUpload && list.querySelector(".attachment-item.is-done")) {
      window.location.reload();
    }
  };

  input.addEventListener("change", () => uploadFiles(input.files));

  ["dragenter", "dragover"].forEach((type) => {
    drop.addEventListener(type, (event) => {
      event.preventDefault();
      drop.classList.add("is-dragging");
    });
  });

  ["dragleave", "drop"].forEach((type) => {
    drop.addEventListener(type, (event) => {
      event.preventDefault();
      drop.classList.remove("is-dragging");
    });
  });

  drop.addEventListener("drop", (event) => {
    uploadFiles(event.dataTransfer?.files);
  });

  if (editor) {
    editor.addEventListener("paste", (event) => {
      const images = pastedImages(event);
      if (!images.length) return;
      event.preventDefault();
      void uploadFiles(images);
    });
  }
}

function initMarkdownEditor() {
  const root = document.querySelector("[data-markdown-editor]");
  if (!root) return;

  const editor = root.querySelector("#content");
  const heading = root.querySelector("[data-markdown-heading]");
  const count = root.querySelector("[data-markdown-count]");
  if (!(editor instanceof HTMLTextAreaElement)) return;

  const updateCount = () => {
    if (count) count.textContent = sblogText("character_count", "{count} 字符", { count: editor.value.length });
  };

  const replaceSelection = (replacement, selectionOffset = replacement.length, selectionLength = 0) => {
    const start = editor.selectionStart ?? editor.value.length;
    const end = editor.selectionEnd ?? editor.value.length;
    const scrollTop = editor.scrollTop;
    editor.setRangeText(replacement, start, end, "end");
    editor.focus({ preventScroll: true });
    editor.setSelectionRange(start + selectionOffset, start + selectionOffset + selectionLength);
    editor.scrollTop = scrollTop;
    editor.dispatchEvent(new Event("input", { bubbles: true }));
  };

  const wrapSelection = (before, after, placeholder) => {
    const selected = editor.value.slice(editor.selectionStart, editor.selectionEnd);
    const content = selected || placeholder;
    replaceSelection(`${before}${content}${after}`, before.length, content.length);
  };

  const prefixLines = (prefixForIndex, removablePattern, toggle = true) => {
    const value = editor.value;
    const selectionStart = editor.selectionStart;
    const selectionEnd = editor.selectionEnd;
    const lineStart = value.lastIndexOf("\n", Math.max(0, selectionStart - 1)) + 1;
    const nextBreak = value.indexOf("\n", selectionEnd);
    const lineEnd = nextBreak === -1 ? value.length : nextBreak;
    const lines = value.slice(lineStart, lineEnd).split("\n");
    const shouldRemove = toggle && removablePattern && lines.every((line) => line === "" || removablePattern.test(line));
    const transformed = lines.map((line, index) => {
      if (line === "") return line;
      if (shouldRemove) return line.replace(removablePattern, "");
      return `${prefixForIndex(index)}${line.replace(removablePattern || /$^/, "")}`;
    }).join("\n");
    const scrollTop = editor.scrollTop;
    editor.setRangeText(transformed, lineStart, lineEnd, "select");
    editor.focus({ preventScroll: true });
    editor.scrollTop = scrollTop;
    editor.dispatchEvent(new Event("input", { bubbles: true }));
  };

  const insertBlock = (block, cursorOffset = block.length, selectionLength = 0) => {
    const start = editor.selectionStart;
    const before = editor.value.slice(0, start);
    const after = editor.value.slice(editor.selectionEnd);
    const leading = before === "" || before.endsWith("\n\n") ? "" : before.endsWith("\n") ? "\n" : "\n\n";
    const trailing = after === "" || after.startsWith("\n\n") ? "" : after.startsWith("\n") ? "\n" : "\n\n";
    replaceSelection(`${leading}${block}${trailing}`, leading.length + cursorOffset, selectionLength);
  };

  const applyAction = (action) => {
    if (action === "bold") wrapSelection("**", "**", sblogText("bold_text", "粗体文本"));
    if (action === "italic") wrapSelection("*", "*", sblogText("italic_text", "斜体文本"));
    if (action === "strike") wrapSelection("~~", "~~", sblogText("strikethrough_text", "删除线文本"));
    if (action === "inline-code") wrapSelection("`", "`", sblogText("code", "代码"));
    if (action === "quote") prefixLines(() => "> ", /^>\s?/);
    if (action === "unordered-list") prefixLines(() => "- ", /^[-*+]\s+/);
    if (action === "ordered-list") prefixLines((index) => `${index + 1}. `, /^\d+[.)]\s+/);
    if (action === "task-list") prefixLines(() => "- [ ] ", /^[-*+]\s+\[[ xX]]\s+/);
    if (action === "link") {
      const selected = editor.value.slice(editor.selectionStart, editor.selectionEnd);
      const isUrl = /^https?:\/\/\S+$/i.test(selected);
      const label = isUrl ? sblogText("link_text", "链接文字") : (selected || sblogText("link_text", "链接文字"));
      const url = isUrl ? selected : "https://";
      const replacement = `[${label}](${url})`;
      const selectionOffset = isUrl ? 1 : replacement.indexOf(url);
      const selectionLength = isUrl ? label.length : url.length;
      replaceSelection(replacement, selectionOffset, selectionLength);
    }
    if (action === "image") {
      const selected = editor.value.slice(editor.selectionStart, editor.selectionEnd) || sblogText("image_description", "图片描述");
      const replacement = `![${selected}](https://)`;
      const urlStart = replacement.indexOf("https://");
      replaceSelection(replacement, urlStart, "https://".length);
    }
    if (action === "table") {
      const firstHeading = editor.value.slice(editor.selectionStart, editor.selectionEnd) || sblogText("column_1", "列 1");
      const column2 = sblogText("column_2", "列 2");
      const column3 = sblogText("column_3", "列 3");
      const content = sblogText("table_content", "内容");
      const table = `| ${firstHeading} | ${column2} | ${column3} |\n| --- | --- | --- |\n| ${content} | ${content} | ${content} |`;
      insertBlock(table, 2, firstHeading.length);
    }
    if (action === "code-block") {
      const selected = editor.value.slice(editor.selectionStart, editor.selectionEnd) || sblogText("enter_code_here", "在这里输入代码");
      const block = `\`\`\`\n${selected}\n\`\`\``;
      insertBlock(block, 4);
    }
    if (action === "horizontal-rule") insertBlock("---");
  };

  root.querySelectorAll("[data-markdown-action]").forEach((button) => {
    button.addEventListener("click", () => applyAction(button.dataset.markdownAction || ""));
  });

  heading?.addEventListener("change", () => {
    if (!heading.value) return;
    const level = Math.min(3, Math.max(1, Number(heading.value) || 2));
    prefixLines(() => `${"#".repeat(level)} `, /^#{1,6}\s+/, false);
    heading.value = "";
  });

  editor.addEventListener("keydown", (event) => {
    if (!(event.ctrlKey || event.metaKey)) return;
    const key = event.key.toLowerCase();
    const shortcuts = { b: "bold", i: "italic", k: "link" };
    if (!shortcuts[key]) return;
    event.preventDefault();
    applyAction(shortcuts[key]);
  });
  editor.addEventListener("input", updateCount);
  updateCount();
}

function initAiEditor() {
  const root = document.querySelector("[data-ai-editor]");
  if (!root) return;

  const content = document.getElementById("content");
  const modal = root.querySelector("[data-ai-modal]");
  const instruction = root.querySelector("#ai_instruction");
  const status = root.querySelector("[data-ai-status]");
  const confirm = root.querySelector("[data-ai-confirm]");

  const generate = async (source, extraInstruction = "") => {
    const data = new FormData();
    data.append("csrf_token", root.dataset.csrf || "");
    data.append("type", "polish");
    data.append("content", source);
    data.append("instruction", extraInstruction);

    const response = await fetch(root.dataset.url || "", {
      method: "POST",
      body: data,
      credentials: "same-origin",
    });
    const result = await response
      .json()
      .catch(() => ({ ok: false, error: sblogText("ai_invalid_response", "AI 服务返回了无法解析的响应。") }));

    if (!response.ok || !result.ok) {
      throw new Error(result.error || sblogText("ai_generation_failed", "AI 生成失败。"));
    }
    return result.result || "";
  };

  document.querySelectorAll('[data-ai-action="polish"]').forEach((button) => {
    button.addEventListener("click", () => {
      modal.hidden = false;
      instruction.focus();
    });
  });

  root.querySelectorAll("[data-ai-close]").forEach((button) => {
    button.addEventListener("click", () => {
      modal.hidden = true;
      status.textContent = "";
    });
  });

  confirm.addEventListener("click", async () => {
    confirm.disabled = true;
    status.textContent = sblogText("ai_processing_content", "AI 正在处理正文...");

    try {
      content.value = await generate(content.value, instruction.value.trim());
      content.dispatchEvent(new Event("input", { bubbles: true }));
      modal.hidden = true;
      status.textContent = "";
      content.focus();
    } catch (error) {
      status.textContent = error instanceof Error ? error.message : sblogText("ai_generation_failed", "AI 生成失败。");
    } finally {
      confirm.disabled = false;
    }
  });
}
document.addEventListener("DOMContentLoaded", () => {
  initAdminTheme();
  initThemeManager();
  initPasswordToggles();
  initAdminNavigation();
  initAccountMenus();
  initSettingsControls();
  initTagManager();
  initMarkdownEditor();
  initAttachmentUploader();
  initAiEditor();
});
