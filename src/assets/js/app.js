(function () {
  const base = window.APP_BASE || "";

  // チャート：空き部分クリックで新規予約（会場・時刻を引き継ぐ）
  const chart = document.querySelector(".chart");
  if (chart) {
    const start = Number(chart.dataset.start), end = Number(chart.dataset.end);
    chart.querySelectorAll(".chart-track[data-venue]").forEach((track) => {
      track.addEventListener("click", (ev) => {
        if (ev.target.closest(".bar")) return;
        const rect = track.getBoundingClientRect();
        const ratio = Math.min(1, Math.max(0, (ev.clientX - rect.left) / rect.width));
        let min = Math.floor((start * 60 + ratio * (end - start) * 60) / 30) * 30;
        min = Math.min(min, 23 * 60);
        const time = String(Math.floor(min / 60)).padStart(2, "0") + ":" + String(min % 60).padStart(2, "0");
        const q = new URLSearchParams(window.location.search);
        ["edit"].forEach((k) => q.delete(k));
        q.set("date", chart.dataset.date); q.set("new", "1"); q.set("time", time); q.set("venue", track.dataset.venue);
        window.location.search = q.toString();
      });
    });
  }

  const form = document.getElementById("rsvForm");
  if (!form) return;

  // 会場の絞り込み
  const vf = form.querySelector(".venue-filter");
  vf?.addEventListener("input", () => {
    const t = vf.value.trim();
    form.querySelectorAll(".pick").forEach((p) => { p.style.display = !t || p.textContent.includes(t) || p.querySelector("input").checked ? "" : "none"; });
  });

  // 注文行
  const tbody = form.querySelector("#orders tbody");
  const total = () => {
    let s = 0;
    tbody.querySelectorAll("tr").forEach((tr) => {
      const p = Number(String(tr.querySelector('[name="orders[unit_price][]"]').value).replace(/[^\d-]/g, "")) || 0;
      const q = Number(tr.querySelector('[name="orders[qty][]"]').value) || 0;
      s += p * q;
    });
    form.querySelector("#orderTotal").textContent = s.toLocaleString("ja-JP");
  };
  const bindRow = (tr) => {
    tr.querySelector(".order-menu").addEventListener("change", (e) => {
      const o = e.target.selectedOptions[0];
      if (o && o.value) {
        tr.querySelector('[name="orders[name][]"]').value = o.dataset.name;
        tr.querySelector('[name="orders[unit_price][]"]').value = o.dataset.price;
      }
      total();
    });
    tr.querySelector(".order-del").addEventListener("click", () => {
      if (tbody.querySelectorAll("tr").length > 1) tr.remove();
      else { tr.querySelectorAll("input").forEach((i) => { i.value = i.type === "number" ? 1 : ""; }); tr.querySelector(".order-menu").value = ""; }
      total();
    });
    tr.querySelectorAll("input").forEach((i) => i.addEventListener("input", total));
  };
  tbody.querySelectorAll("tr").forEach(bindRow);
  form.querySelector("#orderAdd").addEventListener("click", () => {
    const tr = tbody.querySelector("tr:last-child").cloneNode(true);
    tr.querySelectorAll("input").forEach((i) => { i.value = i.type === "number" ? 1 : ""; });
    tr.querySelector("select").value = "";
    tbody.appendChild(tr); bindRow(tr);
  });
  total();

  // 顧客検索
  const cid = form.querySelector("#customerId"), box = form.querySelector("#custSearchBox"), cur = form.querySelector("#custCurrent");
  const input = form.querySelector("#custSearch"), list = form.querySelector("#custResults");
  let timer = 0;
  input.addEventListener("input", () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      const q = input.value.trim();
      list.replaceChildren();
      if (!q) return;
      const res = await fetch(`${base}/api/customers?q=${encodeURIComponent(q)}`, { credentials: "same-origin" });
      const rows = res.ok ? await res.json() : [];
      if (!rows.length) { const li = document.createElement("li"); li.textContent = "該当なし（下の「新しい顧客として登録」から入力）"; li.className = "muted"; list.appendChild(li); return; }
      rows.forEach((r) => {
        const li = document.createElement("li");
        li.textContent = r.label + (r.tel ? `　${r.tel}` : "");
        li.addEventListener("click", () => {
          cid.value = r.id; form.querySelector("#custLabel").textContent = r.label;
          cur.hidden = false; box.hidden = true; list.replaceChildren(); input.value = "";
        });
        list.appendChild(li);
      });
    }, 250);
  });
  form.querySelector("#custClear").addEventListener("click", () => { cid.value = ""; cur.hidden = true; box.hidden = false; input.focus(); });

  // 開始時刻を変えたら終了時刻も同じ長さでずらす
  const st = form.querySelector('[name="start_time"]'), et = form.querySelector('[name="end_time"]');
  const toMin = (v) => { const m = /^(\d{2}):(\d{2})$/.exec(v); return m ? Number(m[1]) * 60 + Number(m[2]) : null; };
  let prev = toMin(st.value);
  st.addEventListener("change", () => {
    const s = toMin(st.value), e = toMin(et.value);
    if (s !== null && e !== null && prev !== null) {
      const n = Math.min(23 * 60 + 59, s + (e - prev));
      et.value = String(Math.floor(n / 60)).padStart(2, "0") + ":" + String(n % 60).padStart(2, "0");
    }
    prev = s;
  });
})();
