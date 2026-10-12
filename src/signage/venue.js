(function () {
  const API_URL = "../api/signage/events";
  const AUTH_API_URL = "./login.php";
  const AUTH_KEY = "itaya-signage-admin-auth";
  const AUTH_CSRF_KEY = "itaya-signage-admin-csrf";
  const VENUE_TIME_ZONE = "Asia/Tokyo";
  const VENUE_PAGE_SIZE = 10;
  const VENUE_TAB_DAYS = 7;
  const SLIDE_SECONDS = 5;
  const SLIDE_TRANSITION_MS = 760;

  const params = new URLSearchParams(window.location.search);
  const pageScreen = document.body?.dataset?.screen || "";
  let state = { ok: true, syncedAt: "", events: [], filters: { status: [], flags: [] }, venueLocations: {}, venueAliases: {} };
  let selectedDate = currentDateString();
  let renderToken = 0;

  function createEl(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  }

  function venueDateTimeParts(date = new Date()) {
    const parts = new Intl.DateTimeFormat("en-CA", {
      timeZone: VENUE_TIME_ZONE,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      hourCycle: "h23"
    }).formatToParts(date);
    return Object.fromEntries(parts.filter((part) => part.type !== "literal").map((part) => [part.type, part.value]));
  }

  function currentDateString() {
    const parts = venueDateTimeParts();
    return `${parts.year}-${parts.month}-${parts.day}`;
  }

  function currentTimeString() {
    const parts = venueDateTimeParts();
    return `${parts.hour}:${parts.minute}`;
  }

  function normalizeDateString(value) {
    const text = String(value || "").trim();
    const match = text.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (!match) return "";
    return `${match[1]}-${String(match[2]).padStart(2, "0")}-${String(match[3]).padStart(2, "0")}`;
  }

  function normalizeTimeString(value) {
    const match = String(value || "").trim().match(/^(\d{1,2}):([0-5]\d)$/);
    if (!match) return "";
    const hour = Number(match[1]);
    if (!Number.isInteger(hour) || hour < 0 || hour > 23) return "";
    return `${String(hour).padStart(2, "0")}:${match[2]}`;
  }

  function cleanText(value, maxLength) {
    return String(value || "")
      .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F\u007F]/g, "")
      .trim()
      .slice(0, maxLength);
  }

  function parseFilterTerms(value) {
    const source = Array.isArray(value) ? value.join(",") : String(value || "");
    return source
      .split(",")
      .map((item) => cleanText(item, 80).toLowerCase())
      .filter(Boolean)
      .slice(0, 30);
  }

  function normalizeFilters(filters) {
    return {
      status: parseFilterTerms(filters?.status),
      flags: parseFilterTerms(filters?.flags)
    };
  }

  function filtersToInputValue(terms) {
    return parseFilterTerms(terms).join(", ");
  }

  function looksLikeHash(value) {
    const text = cleanText(value, 120);
    return /^[a-f0-9]{16,}$/i.test(text)
      || /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i.test(text)
      || /^[A-Za-z0-9_-]{24,}$/.test(text);
  }

  function displayFlags(event) {
    return (Array.isArray(event.flags) ? event.flags : [])
      .map((flag) => cleanText(flag, 80))
      .filter((flag) => flag && !looksLikeHash(flag));
  }

  function eventMatchesFilter(event) {
    const filters = normalizeFilters(state.filters);
    const flagText = [
      ...(Array.isArray(event.flags) ? event.flags : []),
      ...(Array.isArray(event.flagIds) ? event.flagIds : [])
    ].map((item) => cleanText(item, 80).toLowerCase()).join(",");
    return filters.flags.some((term) => flagText.includes(term));
  }

  function displayVenueName(venue) {
    const trimmed = cleanText(venue, 120);
    if (trimmed === "チェス1" || trimmed === "チェス①") return "チェスナット";
    return trimmed;
  }

  function venueBaseName(venue) {
    return displayVenueName(venue).replace(/[（(][^）)]*[）)]$/, "");
  }

  function normalizeVenueLocations(locations) {
    if (!locations || typeof locations !== "object" || Array.isArray(locations)) return {};
    return Object.fromEntries(Object.entries(locations)
      .map(([venue, location]) => [cleanText(venue, 120), cleanText(location, 60)])
      .filter(([venue, location]) => venue && location));
  }

  function normalizeVenueAliases(aliases) {
    if (!aliases || typeof aliases !== "object" || Array.isArray(aliases)) return {};
    return Object.fromEntries(Object.entries(aliases)
      .map(([slug, venue]) => [cleanText(slug, 120).toLowerCase(), cleanText(venue, 120)])
      .filter(([slug, venue]) => slug && venue));
  }

  function venueLocationFor(venue) {
    const customLocations = normalizeVenueLocations(state.venueLocations);
    const mergedLocations = customLocations;
    const names = displayVenueName(venue).split(/[,、]/).map((item) => item.trim()).filter(Boolean);
    for (const name of names) {
      const baseName = venueBaseName(name);
      const exactLocation = mergedLocations[name] || mergedLocations[baseName];
      if (exactLocation) return exactLocation;
      const matchedVenue = Object.keys(mergedLocations)
        .sort((a, b) => b.length - a.length)
        .find((venueName) => name.includes(venueName) || baseName.includes(venueName));
      if (matchedVenue) return mergedLocations[matchedVenue];
    }
    return "";
  }

  function formatVenueLines(venue) {
    return displayVenueName(venue).split(/[,、]/).map((item) => item.trim()).filter(Boolean).join("、");
  }

  function eventLocationFor(event) {
    return cleanText(event?.location || "", 60) || venueLocationFor(event?.venue || "");
  }

  function minutesFromTime(time) {
    const [hours, minutes] = String(time || "00:00").split(":").map(Number);
    return hours * 60 + minutes;
  }

  function eventActiveLabel(event, referenceTime) {
    const eventMinutes = minutesFromTime(event.time);
    const referenceMinutes = minutesFromTime(referenceTime);
    if (referenceMinutes >= eventMinutes - 30 && referenceMinutes < eventMinutes) return "もうすぐ開始";
    if (referenceMinutes >= eventMinutes && referenceMinutes <= eventMinutes + 120) return "開催中";
    return "";
  }

  function dateObjectFromString(value) {
    const date = normalizeDateString(value) || currentDateString();
    const [year, month, day] = date.split("-").map(Number);
    return new Date(Date.UTC(year, month - 1, day) - (9 * 60 * 60 * 1000));
  }

  function dateStringFromOffset(offset) {
    const date = dateObjectFromString(currentDateString());
    date.setUTCDate(date.getUTCDate() + offset);
    const parts = venueDateTimeParts(date);
    return `${parts.year}-${parts.month}-${parts.day}`;
  }

  function venueTabDates() {
    return Array.from({ length: VENUE_TAB_DAYS }, (_, index) => dateStringFromOffset(index));
  }

  function shortDateLabel(date) {
    const parts = new Intl.DateTimeFormat("ja-JP", {
      timeZone: VENUE_TIME_ZONE,
      month: "numeric",
      day: "numeric",
      weekday: "short"
    }).formatToParts(dateObjectFromString(date));
    const values = Object.fromEntries(parts.filter((part) => part.type !== "literal").map((part) => [part.type, part.value]));
    return `${values.month}/${values.day}(${values.weekday})`;
  }

  function formatVenueTitleDate(date = new Date()) {
    const parts = new Intl.DateTimeFormat("ja-JP", {
      timeZone: VENUE_TIME_ZONE,
      month: "numeric",
      day: "numeric",
      weekday: "short"
    }).formatToParts(date);
    const values = Object.fromEntries(parts.filter((part) => part.type !== "literal").map((part) => [part.type, part.value]));
    return `${values.month}月${values.day}日（${values.weekday}）の会場案内`;
  }

  function sortEvents(events) {
    return [...events].sort((a, b) => {
      const dateDiff = String(a.date || "").localeCompare(String(b.date || ""), "ja");
      if (dateDiff !== 0) return dateDiff;
      const diff = minutesFromTime(a.time) - minutesFromTime(b.time);
      if (diff !== 0) return diff;
      return String(a.venue || "").localeCompare(String(b.venue || ""), "ja");
    });
  }

  function normalizeEvents(events) {
    if (!Array.isArray(events)) return [];
    return events.map((event) => ({
      id: cleanText(event.id || crypto.randomUUID(), 100),
      date: normalizeDateString(event.date) || currentDateString(),
      time: normalizeTimeString(event.time),
      endTime: normalizeTimeString(event.endTime),
      durationMinutes: Number(event.durationMinutes) || 0,
      venue: displayVenueName(event.venue),
      location: cleanText(event.location || "", 60),
      name: cleanText(event.name || "", 240),
      status: cleanText(event.status || "", 80),
      flags: Array.isArray(event.flags) ? event.flags.map((flag) => cleanText(flag, 80)).filter(Boolean) : [],
      flagIds: Array.isArray(event.flagIds) ? event.flagIds.map((flagId) => cleanText(flagId, 80)).filter(Boolean) : [],
      visibleOnSignage: event.visibleOnSignage !== false
    })).filter((event) => event.time && event.venue && event.name);
  }

  function eventsForDate(date) {
    const targetDate = normalizeDateString(date) || currentDateString();
    return sortEvents(normalizeEvents(state.events)).filter((event) => (
      event.visibleOnSignage !== false
      && event.date === targetDate
      && !eventMatchesFilter(event)
    ));
  }

  function adminEventsForDate(date) {
    const targetDate = normalizeDateString(date) || currentDateString();
    return sortEvents(normalizeEvents(state.events)).filter((event) => (
      event.visibleOnSignage !== false
      && event.date === targetDate
    ));
  }

  function pageIndex(events) {
    const pageCount = Math.ceil(events.length / VENUE_PAGE_SIZE);
    if (!pageCount) return 0;
    return Math.floor(Date.now() / (SLIDE_SECONDS * 1000)) % pageCount;
  }

  function pagedEvents(events) {
    const pages = [];
    for (let index = 0; index < events.length; index += VENUE_PAGE_SIZE) {
      pages.push(events.slice(index, index + VENUE_PAGE_SIZE));
    }
    if (!pages.length) return [];
    return pages[pageIndex(events)];
  }

  function renderKey(date, time) {
    const events = eventsForDate(date);
    const visibleEvents = pagedEvents(events);
    const eventKey = visibleEvents.map((event) => `${event.id}:${event.time}:${event.venue}:${event.name}:${eventActiveLabel(event, time)}`).join(",");
    return ["venue-auto", state.syncedAt || "", date, time, events.length, pageIndex(events), eventKey].join("|");
  }

  function renderVenueScreen(date = currentDateString(), time = currentTimeString()) {
    const events = pagedEvents(eventsForDate(date));
    const screen = createEl("section", "signage-screen venue-screen light-mode");
    const header = createEl("header", "venue-header");
    header.appendChild(createEl("h1", "", formatVenueTitleDate(dateObjectFromString(date))));

    const list = createEl("div", "venue-list");
    if (!events.length) {
      list.appendChild(createEl("div", "empty-events", "現在表示する会場案内はありません"));
    } else {
      events.forEach((event) => {
        const card = createEl("article", "venue-card");
        const activeLabel = eventActiveLabel(event, time);
        if (activeLabel) card.classList.add("is-active-window");

        const timeBox = createEl("div", "venue-time");
        if (activeLabel) timeBox.appendChild(createEl("div", "venue-status-label", activeLabel));
        timeBox.appendChild(createEl("span", "", event.time));
        card.appendChild(timeBox);

        const detail = createEl("div", "venue-detail");
        const roomLine = createEl("div", "venue-room-line");
        const location = eventLocationFor(event);
        if (location) roomLine.appendChild(createEl("span", "venue-location-badge", location));
        roomLine.appendChild(createEl("div", "venue-room", formatVenueLines(event.venue)));
        detail.appendChild(roomLine);
        detail.appendChild(createEl("div", "venue-divider"));
        detail.appendChild(createEl("div", "venue-name", event.name));
        card.appendChild(detail);
        list.appendChild(card);
      });
    }

    const footer = createEl("footer", "venue-footer");
    const footerLogo = document.createElement("img");
    footerLogo.src = "./assets/logo_itaya.png";
    footerLogo.alt = "HOTEL NEW ITAYA";
    footer.appendChild(footerLogo);
    screen.append(header, list, footer);
    return screen;
  }

  async function readJsonResponse(response) {
    const text = await response.text();
    let parsed = null;
    try { parsed = text ? JSON.parse(text) : null; } catch {}
    if (!response.ok) {
      const detail = parsed?.error || text.replace(/\s+/g, " ").trim().slice(0, 160);
      throw new Error(`HTTP ${response.status}${detail ? `: ${detail}` : ""}`);
    }
    if (!parsed || typeof parsed !== "object") {
      throw new Error("サーバーから不正な応答が返りました。");
    }
    return parsed;
  }
  async function fetchWithTimeout(url, options = {}, timeoutMs = 15000) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);
    try {
      return await fetch(url, { ...options, signal: controller.signal });
    } finally {
      window.clearTimeout(timer);
    }
  }
  async function fetchTableCheck(options = {}) {
    const query = new URLSearchParams({ t: String(Date.now()) });
    if (options.sync) query.set("sync", "1");
    const feedDate = normalizeDateString(options.date || params.get("date"));
    if (feedDate) query.set("date", feedDate);
    const response = await fetch(`${API_URL}?${query}`, { cache: "no-store" });
    const parsed = await readJsonResponse(response);
    state = {
      ok: parsed?.ok !== false,
      error: parsed?.error || "",
      syncedAt: parsed?.syncedAt || "",
      events: normalizeEvents(parsed?.events || []),
      filters: normalizeFilters(parsed?.filters),
      venueLocations: normalizeVenueLocations(parsed?.venueLocations),
      venueAliases: normalizeVenueAliases(parsed?.venueAliases)
    };
    return state;
  }

  async function postTableCheckSync() {
    const csrfToken = sessionStorage.getItem(AUTH_CSRF_KEY) || "";
    const response = await fetchWithTimeout(API_URL, {
      method: "POST",
      headers: csrfToken ? { "X-CSRF-Token": csrfToken } : {}
    });
    const parsed = await readJsonResponse(response);
    state = {
      ok: parsed?.ok !== false,
      error: parsed?.error || "",
      syncedAt: parsed?.syncedAt || "",
      events: normalizeEvents(parsed?.events || []),
      filters: normalizeFilters(parsed?.filters),
      venueLocations: normalizeVenueLocations(parsed?.venueLocations),
      venueAliases: normalizeVenueAliases(parsed?.venueAliases)
    };
    return state;
  }

  async function saveTableCheckFilters() {
    const csrfToken = sessionStorage.getItem(AUTH_CSRF_KEY) || "";
    const filters = normalizeFilters({
      flags: document.getElementById("tablecheckFlagFilter")?.value || ""
    });
    const response = await fetchWithTimeout(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(csrfToken ? { "X-CSRF-Token": csrfToken } : {})
      },
      body: JSON.stringify({ action: "saveFilters", filters })
    });
    const parsed = await readJsonResponse(response);
    state = {
      ok: parsed?.ok !== false,
      error: parsed?.error || "",
      syncedAt: parsed?.syncedAt || "",
      events: normalizeEvents(parsed?.events || []),
      filters: normalizeFilters(parsed?.filters),
      venueLocations: normalizeVenueLocations(parsed?.venueLocations),
      venueAliases: normalizeVenueAliases(parsed?.venueAliases)
    };
    return state;
  }

  async function saveTableCheckVenueLocations(locations) {
    const csrfToken = sessionStorage.getItem(AUTH_CSRF_KEY) || "";
    const response = await fetchWithTimeout(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(csrfToken ? { "X-CSRF-Token": csrfToken } : {})
      },
      body: JSON.stringify({ action: "saveVenueLocations", venueLocations: locations })
    });
    const parsed = await readJsonResponse(response);
    state = {
      ok: parsed?.ok !== false,
      error: parsed?.error || "",
      syncedAt: parsed?.syncedAt || "",
      events: normalizeEvents(parsed?.events || []),
      filters: normalizeFilters(parsed?.filters),
      venueLocations: normalizeVenueLocations(parsed?.venueLocations),
      venueAliases: normalizeVenueAliases(parsed?.venueAliases)
    };
    return state;
  }

  async function saveTableCheckVenueAliases(aliases) {
    const csrfToken = sessionStorage.getItem(AUTH_CSRF_KEY) || "";
    const response = await fetchWithTimeout(API_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        ...(csrfToken ? { "X-CSRF-Token": csrfToken } : {})
      },
      body: JSON.stringify({ action: "saveVenueAliases", venueAliases: aliases })
    });
    const parsed = await readJsonResponse(response);
    state = {
      ok: parsed?.ok !== false,
      error: parsed?.error || "",
      syncedAt: parsed?.syncedAt || "",
      events: normalizeEvents(parsed?.events || []),
      filters: normalizeFilters(parsed?.filters),
      venueLocations: normalizeVenueLocations(parsed?.venueLocations),
      venueAliases: normalizeVenueAliases(parsed?.venueAliases)
    };
    return state;
  }

  function renderVenueLocationRows() {
    const mount = document.getElementById("tablecheckVenueLocationRows");
    if (!mount) return;
    mount.replaceChildren();
    const entries = Object.entries(normalizeVenueLocations(state.venueLocations));
    if (!entries.length) {
      mount.appendChild(createEl("p", "panel-note", "登録されている対応表はありません。"));
      return;
    }
    entries.forEach(([venue, location]) => {
      const row = createEl("div", "tablecheck-location-row");
      const venueInput = createEl("input");
      venueInput.type = "text";
      venueInput.value = venue;
      venueInput.dataset.locationVenue = "true";
      venueInput.setAttribute("aria-label", "会場名");
      const locationInput = createEl("input");
      locationInput.type = "text";
      locationInput.value = location;
      locationInput.dataset.locationValue = "true";
      locationInput.setAttribute("aria-label", "場所");
      const removeButton = createEl("button", "tablecheck-location-remove", "削除");
      removeButton.type = "button";
      removeButton.addEventListener("click", () => row.remove());
      row.append(venueInput, locationInput, removeButton);
      mount.appendChild(row);
    });
  }

  function readVenueLocationRows() {
    const locations = {};
    document.querySelectorAll(".tablecheck-location-row").forEach((row) => {
      const venue = cleanText(row.querySelector("[data-location-venue]")?.value || "", 120);
      const location = cleanText(row.querySelector("[data-location-value]")?.value || "", 60);
      if (venue && location) locations[venue] = location;
    });
    return locations;
  }

  function renderVenueAliasRows() {
    const mount = document.getElementById("tablecheckVenueAliasRows");
    if (!mount) return;
    mount.replaceChildren();
    const entries = Object.entries(normalizeVenueAliases(state.venueAliases));
    if (!entries.length) {
      mount.appendChild(createEl("p", "panel-note", "登録されている変換設定はありません。"));
      return;
    }
    entries.forEach(([slug, venue]) => {
      const row = createEl("div", "tablecheck-location-row");
      const slugInput = createEl("input");
      slugInput.type = "text";
      slugInput.value = slug;
      slugInput.dataset.aliasSlug = "true";
      slugInput.setAttribute("aria-label", "店舗スラッグ");
      const venueInput = createEl("input");
      venueInput.type = "text";
      venueInput.value = venue;
      venueInput.dataset.aliasVenue = "true";
      venueInput.setAttribute("aria-label", "表示会場名");
      const removeButton = createEl("button", "tablecheck-location-remove", "削除");
      removeButton.type = "button";
      removeButton.addEventListener("click", () => row.remove());
      row.append(slugInput, venueInput, removeButton);
      mount.appendChild(row);
    });
  }

  function readVenueAliasRows() {
    const aliases = {};
    document.querySelectorAll("[data-alias-slug]").forEach((slugInput) => {
      const row = slugInput.closest(".tablecheck-location-row");
      const slug = cleanText(slugInput.value || "", 120).toLowerCase();
      const venue = cleanText(row?.querySelector("[data-alias-venue]")?.value || "", 120);
      if (slug && venue) aliases[slug] = venue;
    });
    return aliases;
  }

  async function renderSignage(mount, options = {}) {
    const date = normalizeDateString(options.previewDate || params.get("date")) || currentDateString();
    const time = normalizeTimeString(options.previewTime || params.get("time")) || currentTimeString();
    const nextKey = renderKey(date, time);
    if (mount.dataset.renderKey === nextKey && mount.firstElementChild) return;
    const token = ++renderToken;
    const screen = renderVenueScreen(date, time);
    if (token !== renderToken) return;
    screen.dataset.renderKey = nextKey;
    screen.classList.add("is-entering");
    const previous = mount.querySelector(".signage-screen");
    mount.dataset.renderKey = nextKey;
    mount.appendChild(screen);
    window.requestAnimationFrame(() => {
      screen.classList.add("is-visible");
      if (previous) previous.classList.add("is-leaving");
    });
    if (previous) {
      window.setTimeout(() => previous.remove(), SLIDE_TRANSITION_MS);
    }
  }

  function isAdminAuthenticated() {
    return sessionStorage.getItem(AUTH_KEY) === "true";
  }

  async function fetchAuthStatus() {
    try {
      const csrfToken = sessionStorage.getItem(AUTH_CSRF_KEY) || "";
      const response = await fetch(`./login.php?t=${Date.now()}`, {
        cache: "no-store",
        headers: csrfToken ? { "X-CSRF-Token": csrfToken } : {}
      });
      if (!response.ok) return false;
      const result = await response.json();
      if (result?.authenticated === true && result.csrfToken) {
        sessionStorage.setItem(AUTH_KEY, "true");
        sessionStorage.setItem(AUTH_CSRF_KEY, result.csrfToken);
        return true;
      }
    } catch (error) {
      console.warn("Failed to check admin session", error);
    }
    sessionStorage.removeItem(AUTH_KEY);
    sessionStorage.removeItem(AUTH_CSRF_KEY);
    return false;
  }

  async function loginAdmin(password) {
    const response = await fetch(AUTH_API_URL, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ password })
    });
    if (!response.ok) return false;
    const result = await response.json();
    if (result?.authenticated !== true || !result.csrfToken) return false;
    sessionStorage.setItem(AUTH_KEY, "true");
    sessionStorage.setItem(AUTH_CSRF_KEY, result.csrfToken);
    return true;
  }

  function revealAdmin() {
    document.body.classList.remove("auth-locked");
    document.getElementById("loginApp")?.classList.add("is-hidden");
  }

  async function setupAdminAuth(onAuthenticated) {
    const loginForm = document.getElementById("loginForm");
    if (!loginForm) {
      onAuthenticated();
      return;
    }
    if (isAdminAuthenticated() && await fetchAuthStatus()) {
      revealAdmin();
      onAuthenticated();
      return;
    }
    const passwordInput = document.getElementById("loginPassword");
    const error = document.getElementById("loginError");
    loginForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (error) error.textContent = "";
      const submitButton = loginForm.querySelector('button[type="submit"]');
      if (submitButton) submitButton.disabled = true;
      if (await loginAdmin(passwordInput.value)) {
        revealAdmin();
        onAuthenticated();
      } else if (error) {
        error.textContent = "パスワードが違います。";
      }
      if (submitButton) submitButton.disabled = false;
    });
  }

  function formatSyncedAt(value) {
    if (!value) return "-";
    try {
      return new Intl.DateTimeFormat("ja-JP", {
        timeZone: VENUE_TIME_ZONE,
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit"
      }).format(new Date(value));
    } catch {
      return value;
    }
  }

  function updateStatus() {
    const syncedAt = document.getElementById("tablecheckSyncedAt");
    const count = document.getElementById("tablecheckEventCount");
    const label = document.getElementById("tablecheckStateLabel");
    if (syncedAt) syncedAt.textContent = formatSyncedAt(state.syncedAt);
    if (count) count.textContent = String(state.events.length);
    if (label) label.textContent = state.ok ? "正常" : "取得エラー";
  }

  function renderDateTabs() {
    const mount = document.getElementById("tablecheckDateTabs");
    if (!mount) return;
    const counts = new Map();
    sortEvents(normalizeEvents(state.events)).filter((event) => event.visibleOnSignage !== false).forEach((event) => {
      counts.set(event.date, (counts.get(event.date) || 0) + 1);
    });
    mount.replaceChildren();
    venueTabDates().forEach((date, index) => {
      const button = createEl("button", "venue-date-tab", `${index === 0 ? "本日 " : ""}${shortDateLabel(date)} (${counts.get(date) || 0})`);
      button.type = "button";
      button.role = "tab";
      button.ariaSelected = String(date === selectedDate);
      button.classList.toggle("is-active", date === selectedDate);
      button.addEventListener("click", async () => {
        selectedDate = date;
        const dateInput = document.getElementById("autoPreviewDate");
        if (dateInput) dateInput.value = selectedDate;
        renderAdminList();
        await renderAdminPreview();
      });
      mount.appendChild(button);
    });
  }

  function renderAdminList() {
    const mount = document.getElementById("tablecheckEventList");
    if (!mount) return;
    updateStatus();
    renderDateTabs();
    mount.replaceChildren();
    const events = adminEventsForDate(selectedDate);
    if (!events.length) {
      mount.appendChild(createEl("p", "panel-note", state.error || "この日付のTableCheck予約はありません。"));
      return;
    }
    events.forEach((event) => {
      const filtered = eventMatchesFilter(event);
      const row = createEl("div", "event-row is-visible-on-signage");
      row.classList.toggle("is-filter-hidden", filtered);
      const text = createEl("div");
      text.appendChild(createEl("strong", "", `${event.date}　${event.time}　${formatVenueLines(event.venue)}`));
      text.appendChild(createEl("small", "", event.name));
      const meta = [];
      if (event.status) meta.push(`ステータス: ${event.status}`);
      const flags = displayFlags(event);
      if (flags.length) meta.push(`フラグ: ${flags.join("、")}`);
      if (meta.length) text.appendChild(createEl("small", "tablecheck-event-meta", meta.join(" / ")));
      const actions = createEl("div", "event-row-actions");
      actions.appendChild(filtered
        ? createEl("span", "tablecheck-filter-hidden-label", "フィルタ非表示")
        : createEl("button", "", "自動表示"));
      row.append(text, actions);
      mount.appendChild(row);
    });
  }

  async function renderAdminPreview() {
    const mount = document.getElementById("screenPreview");
    if (!mount) return;
    let frame = mount.querySelector(".monitor-preview-frame");
    if (!frame) {
      frame = createEl("div", "monitor-preview-frame");
      mount.replaceChildren(frame);
    }
    await renderSignage(frame, {
      previewDate: document.getElementById("autoPreviewDate")?.value || selectedDate,
      previewTime: document.getElementById("autoPreviewTime")?.value || currentTimeString()
    });
  }

  async function setupViewer() {
    document.body.classList.add("viewer-mode");
    const mount = document.getElementById("viewerApp");
    await fetchTableCheck({ sync: true });
    await renderSignage(mount);
    window.setInterval(() => renderSignage(mount), 1000);
    window.setInterval(async () => {
      await fetchTableCheck({ sync: true });
      await renderSignage(mount);
    }, 300000);
  }

  async function setupAdmin() {
    selectedDate = currentDateString();
    const dateInput = document.getElementById("autoPreviewDate");
    const timeInput = document.getElementById("autoPreviewTime");
    const flagFilterInput = document.getElementById('tablecheckFlagFilter');
    if (dateInput) dateInput.value = selectedDate;
    if (timeInput) timeInput.value = currentTimeString();


    dateInput?.addEventListener("change", async (event) => {
      selectedDate = normalizeDateString(event.target.value) || currentDateString();
      renderAdminList();
      await renderAdminPreview();
    });
    timeInput?.addEventListener("change", renderAdminPreview);

    document.getElementById("syncTableCheck")?.addEventListener("click", async () => {
      const status = document.getElementById("tablecheckSyncStatus");
      if (status) status.textContent = "取得中...";
      try {
        await postTableCheckSync();
        if (flagFilterInput) flagFilterInput.value = filtersToInputValue(state.filters.flags);
        renderAdminList();
        await renderAdminPreview();
        if (status) status.textContent = state.ok ? "取得しました。" : `取得エラー: ${state.error || "詳細不明"}`;
      } catch (error) {
        if (status) status.textContent = `取得エラー: ${error?.message || "通信に失敗しました。"}`;
      }
    });

    document.getElementById("saveTableCheckFilters")?.addEventListener("click", async () => {
      const status = document.getElementById("tablecheckFilterStatus");
      if (status) status.textContent = "保存中...";
      try {
        await saveTableCheckFilters();
        if (flagFilterInput) flagFilterInput.value = filtersToInputValue(state.filters.flags);
        renderAdminList();
        await renderAdminPreview();
        if (status) status.textContent = state.ok ? "保存しました。" : `保存エラー: ${state.error || "詳細不明"}`;
      } catch (error) {
        if (status) status.textContent = `保存エラー: ${error?.message || "通信に失敗しました。"}`;
      }
    });

    document.getElementById("addTableCheckVenueLocation")?.addEventListener("click", () => {
      const mount = document.getElementById("tablecheckVenueLocationRows");
      if (!mount) return;
      const row = createEl("div", "tablecheck-location-row");
      const venueInput = createEl("input");
      venueInput.type = "text";
      venueInput.placeholder = "例: 桜・東";
      venueInput.dataset.locationVenue = "true";
      venueInput.setAttribute("aria-label", "会場名");
      const locationInput = createEl("input");
      locationInput.type = "text";
      locationInput.placeholder = "例: 南館4F";
      locationInput.dataset.locationValue = "true";
      locationInput.setAttribute("aria-label", "場所");
      const removeButton = createEl("button", "tablecheck-location-remove", "削除");
      removeButton.type = "button";
      removeButton.addEventListener("click", () => row.remove());
      row.append(venueInput, locationInput, removeButton);
      mount.appendChild(row);
      venueInput.focus();
    });

    document.getElementById("saveTableCheckVenueLocations")?.addEventListener("click", async () => {
      const status = document.getElementById("tablecheckVenueLocationStatus");
      if (status) status.textContent = "保存中...";
      try {
        await saveTableCheckVenueLocations(readVenueLocationRows());
        renderVenueLocationRows();
        renderAdminList();
        await renderAdminPreview();
        if (status) status.textContent = state.ok ? "保存しました。" : `保存エラー: ${state.error || "詳細不明"}`;
      } catch (error) {
        if (status) status.textContent = `保存エラー: ${error?.message || "通信に失敗しました。"}`;
      }
    });

    document.getElementById("addTableCheckVenueAlias")?.addEventListener("click", () => {
      const mount = document.getElementById("tablecheckVenueAliasRows");
      if (!mount) return;
      const row = createEl("div", "tablecheck-location-row");
      const slugInput = createEl("input");
      slugInput.type = "text";
      slugInput.placeholder = "例: newitaya-chestnut";
      slugInput.dataset.aliasSlug = "true";
      slugInput.setAttribute("aria-label", "店舗スラッグ");
      const venueInput = createEl("input");
      venueInput.type = "text";
      venueInput.placeholder = "例: チェスナット";
      venueInput.dataset.aliasVenue = "true";
      venueInput.setAttribute("aria-label", "表示会場名");
      const removeButton = createEl("button", "tablecheck-location-remove", "削除");
      removeButton.type = "button";
      removeButton.addEventListener("click", () => row.remove());
      row.append(slugInput, venueInput, removeButton);
      mount.appendChild(row);
      slugInput.focus();
    });

    document.getElementById("saveTableCheckVenueAliases")?.addEventListener("click", async () => {
      const status = document.getElementById("tablecheckVenueAliasStatus");
      if (status) status.textContent = "保存中...";
      try {
        await saveTableCheckVenueAliases(readVenueAliasRows());
        renderVenueAliasRows();
        if (status) status.textContent = state.ok ? "保存しました。次回取得時から反映されます。" : `保存エラー: ${state.error || "詳細不明"}`;
      } catch (error) {
        if (status) status.textContent = `保存エラー: ${error?.message || "通信に失敗しました。"}`;
      }
    });

    try {
      await fetchTableCheck();
      const flagFilterInput = document.getElementById("tablecheckFlagFilter");
      if (flagFilterInput) flagFilterInput.value = filtersToInputValue(state.filters.flags);
      renderVenueLocationRows();
      renderVenueAliasRows();
      renderAdminList();
      await renderAdminPreview();
    } catch (error) {
      state.ok = false;
      state.error = error?.message || "データの読み込みに失敗しました。";
      try { renderVenueLocationRows(); } catch {}
      try { renderVenueAliasRows(); } catch {}
      try { renderAdminList(); } catch {}
    }

    window.setInterval(renderAdminPreview, 1000);
  }

  function boot() {
    if (pageScreen === "venue-auto") {
      setupViewer();
      return;
    }
    setupAdminAuth(setupAdmin);
  }

  boot();
})();

