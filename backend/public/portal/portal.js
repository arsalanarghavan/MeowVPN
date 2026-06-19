(function () {
	"use strict";

	var PORTAL_I18N = {
		fa: {
			transferTitle: "انتقال سرویس",
			transferHint: "انتقال مالکیت سرویس به کاربر دیگر (شناسه عددی یا نام کاربری).",
			transferServiceId: "شناسه سرویس",
			transferTarget: "کاربر مقصد",
			transferSubmit: "انتقال",
			receiptsTitle: "رسیدها",
			receiptsHint: "۱۰ رسید در هر صفحه (جدیدترین اول).",
			receiptsRefresh: "بارگذاری / تازه‌سازی",
			receiptsPrev: "صفحه قبل",
			receiptsNext: "صفحه بعد",
			receiptsColId: "شناسه",
			receiptsColUser: "کاربر",
			receiptsColAmount: "مبلغ",
			receiptsColStatus: "وضعیت",
			receiptsColDate: "تاریخ",
			discountTitle: "کدهای تخفیف",
			discountHint: "فهرست، ایجاد/ویرایش و حذف کدهای تخفیف در محدودهٔ حساب شما.",
			discountLoadList: "بارگذاری لیست",
			discountIdEdit: "شناسه (۰ = جدید)",
			discountCode: "کد",
			discountType: "نوع",
			discountValue: "مقدار",
			discountMaxUses: "حداکثر استفاده (خالی = نامحدود)",
			discountValidFrom: "معتبر از (YYYY-MM-DD HH:MM، اختیاری)",
			discountValidUntil: "معتبر تا (YYYY-MM-DD HH:MM، اختیاری)",
			discountMinOrder: "حداقل سفارش (تومان، اختیاری)",
			discountMaxOrder: "حداکثر مبلغ سفارش (تومان، اختیاری)",
			discountMaxDiscount: "سقف تخفیف (تومان، اختیاری)",
			discountPlanIds: "شناسه پلن‌های مجاز (با کاما، خالی = همه)",
			discountRestrictedUser: "محدود به کاربر (شناسه، اختیاری)",
			discountAllowSection: "مجاز برای:",
			discountAllowNew: "خرید جدید",
			discountAllowRenew: "تمدید",
			discountAllowVol: "افزایش حجم",
			discountAllowUsers: "افزایش کاربر",
			discountActive: "فعال",
			discountSave: "ذخیره کد",
			discountIdDelete: "شناسه برای حذف",
			discountDelete: "حذف با شناسه",
		},
		en: {
			transferTitle: "Service transfer",
			transferHint: "Transfer service ownership to another user (numeric id or username).",
			transferServiceId: "Service ID",
			transferTarget: "Target user",
			transferSubmit: "Transfer",
			receiptsTitle: "Receipts",
			receiptsHint: "10 receipts per page (newest first).",
			receiptsRefresh: "Load / refresh",
			receiptsPrev: "Previous page",
			receiptsNext: "Next page",
			receiptsColId: "ID",
			receiptsColUser: "User",
			receiptsColAmount: "Amount",
			receiptsColStatus: "Status",
			receiptsColDate: "Date",
			discountTitle: "Discount codes",
			discountHint: "List, create/edit, and delete discount codes within your account scope.",
			discountLoadList: "Load list",
			discountIdEdit: "ID (0 = new)",
			discountCode: "Code",
			discountType: "Type",
			discountValue: "Value",
			discountMaxUses: "Max uses (empty = unlimited)",
			discountValidFrom: "Valid from (YYYY-MM-DD HH:MM, optional)",
			discountValidUntil: "Valid until (YYYY-MM-DD HH:MM, optional)",
			discountMinOrder: "Min order (toman, optional)",
			discountMaxOrder: "Max order amount (toman, optional)",
			discountMaxDiscount: "Max discount cap (toman, optional)",
			discountPlanIds: "Allowed plan IDs (comma-separated, empty = all)",
			discountRestrictedUser: "Restricted to user (ID, optional)",
			discountAllowSection: "Allowed for:",
			discountAllowNew: "New purchase",
			discountAllowRenew: "Renewal",
			discountAllowVol: "Add volume",
			discountAllowUsers: "Add user slots",
			discountActive: "Active",
			discountSave: "Save code",
			discountIdDelete: "ID to delete",
			discountDelete: "Delete by ID",
		},
	};

	function portalLang() {
		var lang = (document.documentElement.getAttribute("lang") || "fa").slice(0, 2);
		return lang === "en" ? "en" : "fa";
	}

	function portalT(key) {
		var bucket = PORTAL_I18N[portalLang()] || PORTAL_I18N.fa;
		return bucket[key] || PORTAL_I18N.fa[key] || key;
	}

	function applyPortalI18n(root) {
		$$(("[data-svp-i18n]"), root || document).forEach(function (el) {
			var key = el.getAttribute("data-svp-i18n");
			if (!key) {
				return;
			}
			var text = portalT(key);
			if (el.tagName === "LABEL") {
				var kids = el.childNodes;
				for (var i = 0; i < kids.length; i++) {
					if (kids[i].nodeType === 3) {
						kids[i].textContent = text;
						return;
					}
				}
				el.insertBefore(document.createTextNode(text), el.firstChild);
				return;
			}
			el.textContent = text;
		});
	}

	var toastTimer = null;

	function $$(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	function toast(msg, isError) {
		var box = document.querySelector(".svp-toast");
		if (!box) {
			box = document.createElement("div");
			box.className = "svp-toast";
			var inner = document.createElement("div");
			inner.className = "svp-toast__msg";
			box.appendChild(inner);
			document.body.appendChild(box);
		}
		box.querySelector(".svp-toast__msg").textContent = String(msg || "");
		box.classList.toggle("is-error", !!isError);
		box.classList.add("is-show");
		clearTimeout(toastTimer);
		toastTimer = setTimeout(function () {
			box.classList.remove("is-show");
		}, 1800);
	}

	function copyText(txt) {
		var text = String(txt || "");
		if (!text) {
			toast("چیزی برای کپی نیست", true);
			return;
		}
		if (
			navigator &&
			navigator.clipboard &&
			navigator.clipboard.writeText
		) {
			navigator.clipboard.writeText(text).then(
				function () {
					toast("کپی شد");
				},
				function () {
					legacyCopy(text);
				}
			);
			return;
		}
		legacyCopy(text);
	}

	function legacyCopy(text) {
		var ta = document.createElement("textarea");
		ta.value = text;
		ta.setAttribute("readonly", "");
		ta.style.position = "fixed";
		ta.style.top = "-1000px";
		ta.style.opacity = "0";
		document.body.appendChild(ta);
		ta.select();
		try {
			document.execCommand("copy");
			toast("کپی شد");
		} catch (e) {
			toast("کپی نشد", true);
		}
		document.body.removeChild(ta);
	}

	function b64utf8(input) {
		try {
			return window.btoa(
				unescape(encodeURIComponent(String(input || "")))
			);
		} catch (e) {
			return "";
		}
	}

	function deeplink(kind, subUrl, remark) {
		var enc = encodeURIComponent(subUrl || "");
		var name = encodeURIComponent(remark || "اشتراک");
		switch (kind) {
			case "v2rayng":
				return "v2rayng://install-sub/?url=" + enc + "&name=" + name;
			case "v2rayn":
				return subUrl;
			case "streisand":
				return "streisand://import/" + enc;
			case "shadowrocket":
				return "sub://" + b64utf8(subUrl);
			case "hiddify":
				return (
					"hiddify://install-config?url=" + enc + "&name=" + name
				);
			case "hiddify-alt":
				return "clash://install-config?url=" + enc + "&name=" + name;
			case "fair":
				return "fairvpn://import/" + enc;
			case "sing-box":
				return "sing-box://import-remote-profile?url=" + enc;
			case "nekobox":
				return "sn://subscription?url=" + enc + "&name=" + name;
			case "clashmeta":
				return "clash://install-config?url=" + enc + "&name=" + name;
			default:
				return subUrl;
		}
	}

	function closeAllMenus(except) {
		$$(".svp-apps__col.is-open").forEach(function (el) {
			if (el !== except) {
				el.classList.remove("is-open");
			}
		});
		$$(".svp-gear__menu.is-open").forEach(function (el) {
			if (el !== except) {
				el.classList.remove("is-open");
			}
		});
	}

	function onClick(e) {
		var target = e.target;

		var copyBtn = target.closest && target.closest("[data-copy]");
		if (copyBtn) {
			e.preventDefault();
			copyText(copyBtn.getAttribute("data-copy"));
			return;
		}

		var gearBtn = target.closest && target.closest(".svp-gear");
		if (gearBtn) {
			e.preventDefault();
			var menu = gearBtn.parentElement.querySelector(".svp-gear__menu");
			if (menu) {
				var open = menu.classList.contains("is-open");
				closeAllMenus();
				if (!open) {
					menu.classList.add("is-open");
				}
			}
			return;
		}

		var appBtn = target.closest && target.closest(".svp-apps__btn");
		if (appBtn) {
			e.preventDefault();
			var col = appBtn.parentElement;
			var wasOpen = col.classList.contains("is-open");
			closeAllMenus();
			if (!wasOpen) {
				col.classList.add("is-open");
			}
			return;
		}

		var importBtn =
			target.closest && target.closest("[data-deeplink]");
		if (importBtn) {
			e.preventDefault();
			var card = importBtn.closest(".svp-card");
			var sub =
				(card && card.getAttribute("data-sub")) ||
				(card && card.getAttribute("data-cfg")) ||
				"";
			var remark = (card && card.getAttribute("data-remark")) || "";
			var kind = importBtn.getAttribute("data-deeplink");
			var link = deeplink(kind, sub, remark);
			try {
				window.location.href = link;
			} catch (e2) {
				toast("عدم پشتیبانی توسط مرورگر", true);
			}
			return;
		}

		if (!(target.closest && target.closest(".svp-apps__col")) && !(target.closest && target.closest(".svp-gear, .svp-gear__menu"))) {
			closeAllMenus();
		}
	}

	function onKey(e) {
		if (e.key === "Escape" || e.keyCode === 27) {
			closeAllMenus();
		}
	}

	document.addEventListener("click", onClick, false);
	document.addEventListener("keydown", onKey, false);
})();

(function () {
	"use strict";
	var root = document.querySelector(".svp-admin");
	if (!root) {
		return;
	}
	var isReseller = root.getAttribute("data-is-reseller") === "1";
	if (isReseller) {
		root.querySelectorAll("[data-svp-portal-site-only]").forEach(function (el) {
			el.hidden = true;
		});
	}
	var ajax = root.getAttribute("data-ajax") || "";
	var nonce = root.getAttribute("data-nonce") || "";
	function qp(name) {
		var m = new RegExp("[?&]" + name + "=([^&]*)").exec(
			window.location.search
		);
		return m ? decodeURIComponent(m[1].replace(/\+/g, " ")) : "";
	}
	function post(op, extra) {
		var params = new URLSearchParams();
		params.set("op", op);
		params.set("nonce", nonce);
		params.set("svp_u", qp("svp_u"));
		params.set("svp_e", qp("svp_e"));
		params.set("svp_s", qp("svp_s"));
		if (extra) {
			extra.split("&").forEach(function (pair) {
				var kv = pair.split("=");
				if (kv[0]) {
					params.set(
						decodeURIComponent(kv[0]),
						decodeURIComponent(kv[1] || "")
					);
				}
			});
		}
		return fetch(ajax, {
			method: "POST",
			credentials: "same-origin",
			headers: {
				"Content-Type": "application/x-www-form-urlencoded; charset=UTF-8",
			},
			body: params.toString(),
		}).then(function (r) {
			return r.json();
		});
	}
	function show(id, obj) {
		var el = document.getElementById(id);
		if (el) {
			el.textContent =
				typeof obj === "string" ? obj : JSON.stringify(obj, null, 2);
		}
	}
	function memOpForTab(tab) {
		if (tab === "approved") {
			return "membership_approved_page";
		}
		if (tab === "rejected") {
			return "membership_rejected_page";
		}
		return "membership_pending_page";
	}
	function renderStatsPayload(data) {
		var pre = document.getElementById("svp-adm-stats");
		var tbl = document.getElementById("svp-adm-stats-table");
		var tb = document.getElementById("svp-adm-stats-tbody");
		if (pre) {
			pre.textContent = data && data.text ? String(data.text) : "";
		}
		if (tbl) {
			tbl.hidden = !(data && data.panels && data.panels.length);
		}
		if (tb) {
			tb.textContent = "";
			if (data && data.panels && data.panels.length) {
				data.panels.forEach(function (row) {
					var tr = document.createElement("tr");
					[
						String(row.label || ""),
						String(row.xray_active != null ? row.xray_active : ""),
						String(
							row.xray_inactive != null ? row.xray_inactive : ""
						),
						row.max_online_day > 0
							? String(row.max_online_day)
							: "—",
					].forEach(function (cellText) {
						var td = document.createElement("td");
						td.textContent = cellText;
						tr.appendChild(td);
					});
					tb.appendChild(tr);
				});
			}
		}
		var nav = root.querySelectorAll("[data-svp-stats-day]");
		for (var i = 0; i < nav.length; i++) {
			var b = nav[i];
			var d = b.getAttribute("data-svp-stats-day");
			b.classList.toggle(
				"is-active",
				data &&
					String(data.day_offset != null ? data.day_offset : "0") ===
						String(d != null ? d : "0")
			);
		}
	}
	function rcptFetch() {
		var rcptRoot = document.getElementById("svp-rcpt-root");
		if (!rcptRoot) {
			return;
		}
		var off = parseInt(rcptRoot.getAttribute("data-offset") || "0", 10);
		if (isNaN(off) || off < 0) {
			off = 0;
		}
		post("receipts_page", "offset=" + encodeURIComponent(String(off))).then(
			function (j) {
				if (!j.success || !j.data) {
					return;
				}
				var d = j.data;
				var tbody = document.getElementById("svp-rcpt-tbody");
				var prev = document.querySelector("[data-svp-rcpt-prev]");
				var next = document.querySelector("[data-svp-rcpt-next]");
				if (prev) {
					prev.disabled = !d.has_prev;
				}
				if (next) {
					next.disabled = !d.has_next;
				}
				if (tbody) {
					tbody.textContent = "";
					(d.items || []).forEach(function (it) {
						var tr = document.createElement("tr");
						[
							String(it.id != null ? it.id : ""),
							String(it.user_label || it.user_id || ""),
							String(it.amount != null ? it.amount : ""),
							String(it.status || ""),
							String(it.created_at || ""),
						].forEach(function (txt) {
							var td = document.createElement("td");
							td.textContent = txt;
							tr.appendChild(td);
						});
						tbody.appendChild(tr);
					});
				}
			}
		);
	}
	function memFetch() {
		var memRoot = document.getElementById("svp-mem-root");
		if (!memRoot) {
			return;
		}
		var tab = memRoot.getAttribute("data-tab") || "pending";
		var off = parseInt(memRoot.getAttribute("data-offset") || "0", 10);
		if (isNaN(off) || off < 0) {
			off = 0;
		}
		post(
			memOpForTab(tab),
			"offset=" + encodeURIComponent(String(off))
		).then(function (j) {
			if (!j.success || !j.data) {
				show("svp-mem-detail", j.data || j);
				return;
			}
			var d = j.data;
			var tbody = document.getElementById("svp-mem-tbody");
			var prev = document.querySelector("[data-svp-mem-prev]");
			var next = document.querySelector("[data-svp-mem-next]");
			if (prev) {
				prev.disabled = !d.has_prev;
			}
			if (next) {
				next.disabled = !d.has_next;
			}
			if (tbody) {
				tbody.textContent = "";
				function addBtn(td, cls, attr, val, label) {
					var b = document.createElement("button");
					b.type = "button";
					b.className = cls;
					b.setAttribute(attr, String(val));
					b.textContent = label;
					td.appendChild(b);
					td.appendChild(document.createTextNode(" "));
				}
				(d.items || []).forEach(function (it) {
					var tr = document.createElement("tr");
					[
						String(it.id),
						String(it.label || ""),
						String(it.status || ""),
						String(it.created_at || ""),
					].forEach(function (txt) {
						var td = document.createElement("td");
						td.textContent = txt;
						tr.appendChild(td);
					});
					var tdOps = document.createElement("td");
					tdOps.className = "svp-mem-ops";
					if (tab === "pending") {
						addBtn(
							tdOps,
							"svp-btn svp-btn--small",
							"data-svp-mem-detail",
							it.id,
							"جزئیات"
						);
						addBtn(
							tdOps,
							"svp-btn svp-btn--small",
							"data-svp-mem-approve",
							it.id,
							"تأیید"
						);
						addBtn(
							tdOps,
							"svp-btn svp-btn--small",
							"data-svp-mem-reject",
							it.id,
							"رد"
						);
					} else {
						addBtn(
							tdOps,
							"svp-btn svp-btn--small",
							"data-svp-mem-detail",
							it.id,
							"جزئیات"
						);
					}
					if (tab === "rejected") {
						addBtn(
							tdOps,
							"svp-btn svp-btn--small",
							"data-svp-mem-reopen",
							it.id,
							"برگرد به صف"
						);
					}
					tr.appendChild(tdOps);
					tbody.appendChild(tr);
				});
			}
		});
	}
	root.addEventListener(
		"click",
		function (e) {
			var memRoot = document.getElementById("svp-mem-root");
			var rcptRoot = document.getElementById("svp-rcpt-root");
			if (rcptRoot && rcptRoot.contains(e.target)) {
				if (e.target.closest("[data-svp-rcpt-refresh]")) {
					e.preventDefault();
					rcptFetch();
					return;
				}
				if (e.target.closest("[data-svp-rcpt-prev]")) {
					e.preventDefault();
					var limR = 10;
					var oR =
						parseInt(rcptRoot.getAttribute("data-offset") || "0", 10) -
						limR;
					rcptRoot.setAttribute("data-offset", String(oR < 0 ? 0 : oR));
					rcptFetch();
					return;
				}
				if (e.target.closest("[data-svp-rcpt-next]")) {
					e.preventDefault();
					var limR2 = 10;
					var oR2 =
						parseInt(rcptRoot.getAttribute("data-offset") || "0", 10) +
						limR2;
					rcptRoot.setAttribute("data-offset", String(oR2));
					rcptFetch();
					return;
				}
			}
			if (memRoot && memRoot.contains(e.target)) {
				var tabBtn = e.target.closest("[data-svp-mem-tab]");
				if (tabBtn) {
					e.preventDefault();
					memRoot.setAttribute(
						"data-tab",
						tabBtn.getAttribute("data-svp-mem-tab") || "pending"
					);
					memRoot.setAttribute("data-offset", "0");
					memRoot
						.querySelectorAll("[data-svp-mem-tab]")
						.forEach(function (b) {
							b.classList.toggle(
								"is-active",
								b === tabBtn
							);
						});
					memFetch();
					return;
				}
				if (e.target.closest("[data-svp-mem-refresh]")) {
					e.preventDefault();
					memFetch();
					return;
				}
				if (e.target.closest("[data-svp-mem-prev]")) {
					e.preventDefault();
					var lim = 5;
					var o =
						parseInt(memRoot.getAttribute("data-offset") || "0", 10) -
						lim;
					memRoot.setAttribute("data-offset", String(o < 0 ? 0 : o));
					memFetch();
					return;
				}
				if (e.target.closest("[data-svp-mem-next]")) {
					e.preventDefault();
					var lim2 = 5;
					var o2 =
						parseInt(memRoot.getAttribute("data-offset") || "0", 10) +
						lim2;
					memRoot.setAttribute("data-offset", String(o2));
					memFetch();
					return;
				}
				var det = e.target.closest("[data-svp-mem-detail]");
				if (det) {
					e.preventDefault();
					var uid = det.getAttribute("data-svp-mem-detail");
					post(
						"membership_detail",
						"user_id=" + encodeURIComponent(uid || "")
					).then(function (j) {
						var wrap = document.getElementById("svp-mem-detail-wrap");
						var imgBox = document.getElementById("svp-mem-detail-img");
						var pre = document.getElementById("svp-mem-detail");
						if (wrap) {
							wrap.hidden = false;
						}
						if (imgBox) {
							imgBox.textContent = "";
							if (j.success && j.data && j.data.avatar_url) {
								var im = document.createElement("img");
								im.setAttribute("src", j.data.avatar_url);
								im.setAttribute("alt", "");
								imgBox.appendChild(im);
							}
						}
						if (pre) {
							if (j.success && j.data) {
								var copy = {};
								Object.keys(j.data).forEach(function (k) {
									if (k !== "avatar_url") {
										copy[k] = j.data[k];
									}
								});
								pre.textContent = JSON.stringify(copy, null, 2);
							} else {
								pre.textContent = JSON.stringify(
									j.data || j,
									null,
									2
								);
							}
						}
					});
					return;
				}
				var ap = e.target.closest("[data-svp-mem-approve]");
				if (ap) {
					e.preventDefault();
					post(
						"membership_approve",
						"user_id=" +
							encodeURIComponent(
								ap.getAttribute("data-svp-mem-approve") || ""
							)
					).then(function () {
						memFetch();
					});
					return;
				}
				var rj = e.target.closest("[data-svp-mem-reject]");
				if (rj) {
					e.preventDefault();
					post(
						"membership_reject",
						"user_id=" +
							encodeURIComponent(
								rj.getAttribute("data-svp-mem-reject") || ""
							)
					).then(function () {
						memFetch();
					});
					return;
				}
				var ro = e.target.closest("[data-svp-mem-reopen]");
				if (ro) {
					e.preventDefault();
					post(
						"membership_reopen",
						"user_id=" +
							encodeURIComponent(
								ro.getAttribute("data-svp-mem-reopen") || ""
							)
					).then(function () {
						memFetch();
					});
					return;
				}
			}
			var btn = e.target.closest("[data-svp-admin-op]");
			if (!btn) {
				return;
			}
			var op = btn.getAttribute("data-svp-admin-op");
			if (!op) {
				return;
			}
			e.preventDefault();
			if (op === "stats") {
				var dayAttr = btn.getAttribute("data-svp-stats-day");
				var day =
					dayAttr != null && dayAttr !== ""
						? parseInt(dayAttr, 10)
						: 0;
				if (isNaN(day) || day < 0) {
					day = 0;
				}
				if (day > 7) {
					day = 7;
				}
				post("stats", "day=" + encodeURIComponent(String(day))).then(
					function (j) {
						if (j.success && j.data) {
							renderStatsPayload(j.data);
						} else {
							show("svp-adm-stats", j.data || j);
						}
					}
				);
				return;
			}
			if (op === "create_service") {
				var u = document.getElementById("svp-cr-uid");
				var p = document.getElementById("svp-cr-pid");
				var g = document.getElementById("svp-cr-gb");
				var m = document.getElementById("svp-cr-mode");
				var ex =
					"target_uid=" +
					encodeURIComponent(u ? u.value : "") +
					"&plan_id=" +
					encodeURIComponent(p ? p.value : "") +
					"&volume_gb=" +
					encodeURIComponent(g ? g.value : "") +
					"&mode=" +
					encodeURIComponent(m ? m.value : "");
				post("create_service", ex).then(function (j) {
					show("svp-adm-create", j.success ? j.data : j);
				});
				return;
			}
			if (op === "renew_service") {
				var sid = document.getElementById("svp-rn-sid");
				var md = document.getElementById("svp-rn-mode");
				post(
					"renew_service",
					"service_id=" +
						encodeURIComponent(sid ? sid.value : "") +
						"&mode=" +
						encodeURIComponent(md ? md.value : "")
				).then(function (j) {
					show("svp-adm-renew", j.success ? j.data : j);
				});
				return;
			}
			if (op === "add_volume") {
				var vs = document.getElementById("svp-v-sid");
				var vg = document.getElementById("svp-v-gb");
				var vm = document.getElementById("svp-v-mode");
				post(
					"add_volume",
					"service_id=" +
						encodeURIComponent(vs ? vs.value : "") +
						"&extra_gb=" +
						encodeURIComponent(vg ? vg.value : "") +
						"&mode=" +
						encodeURIComponent(vm ? vm.value : "")
				).then(function (j) {
					show("svp-adm-vol", j.success ? j.data : j);
				});
				return;
			}
			if (op === "service_transfer") {
				var xs = document.getElementById("svp-xfer-sid");
				var xt = document.getElementById("svp-xfer-tgt");
				post(
					"service_transfer",
					"service_id=" +
						encodeURIComponent(xs ? xs.value : "") +
						"&target=" +
						encodeURIComponent(xt ? xt.value : "")
				).then(function (j) {
					show("svp-adm-xfer", j.success ? j.data : j);
				});
				return;
			}
			if (op === "bulk_days") {
				var ack = document.getElementById("svp-bulk-ack");
				if (!ack || !ack.checked) {
					show("svp-adm-bulk", {
						message: "ابتدا کادر تأیید عملیات گروهی را بزنید.",
					});
					return;
				}
				var d = document.getElementById("svp-bulk-d");
				post(
					"bulk_days",
					"days=" +
						encodeURIComponent(d ? d.value : "") +
						"&bulk_ack=1"
				).then(function (j) {
					show("svp-adm-bulk", j.success ? j.data : j);
				});
				return;
			}
			if (op === "bulk_gb") {
				var ackg = document.getElementById("svp-bulk-ack");
				if (!ackg || !ackg.checked) {
					show("svp-adm-bulk", {
						message: "ابتدا کادر تأیید عملیات گروهی را بزنید.",
					});
					return;
				}
				var gb = document.getElementById("svp-bulk-g");
				post(
					"bulk_gb",
					"gb=" + encodeURIComponent(gb ? gb.value : "") + "&bulk_ack=1"
				).then(function (j) {
					show("svp-adm-bulk", j.success ? j.data : j);
				});
				return;
			}
			if (op === "save_crypto") {
				var ak = document.getElementById("svp-cry-api");
				var ip = document.getElementById("svp-cry-ipn");
				var cu = document.getElementById("svp-cry-cur");
				post(
					"save_crypto",
					"api_key=" +
						encodeURIComponent(ak ? ak.value : "") +
						"&ipn_secret=" +
						encodeURIComponent(ip ? ip.value : "") +
						"&pay_currency=" +
						encodeURIComponent(cu ? cu.value : "")
				).then(function (j) {
					show("svp-adm-cry", j.success ? j.data : j);
				});
				return;
			}
			if (op === "rotate_ipn_path") {
				post("rotate_ipn_path", "").then(function (j) {
					show("svp-adm-cry", j.success ? j.data : j);
				});
				return;
			}
			if (op === "referral_load") {
				post("referral_get", "").then(function (j) {
					show("svp-adm-ref", j.success ? j.data : j);
					if (j.success && j.data) {
						var d = j.data;
						var en = document.getElementById("svp-ref-en");
						var pct = document.getElementById("svp-ref-pct");
						var mn = document.getElementById("svp-ref-min");
						var rq = document.getElementById("svp-ref-req");
						var tg = document.getElementById("svp-ref-tg");
						var bl = document.getElementById("svp-ref-bl");
						if (en) en.checked = !!d.referral_enabled;
						if (pct) pct.value = d.referral_percent != null ? String(d.referral_percent) : "";
						if (mn) mn.value = d.referral_min_payout_base != null ? String(d.referral_min_payout_base) : "";
						var exb = document.getElementById("svp-ref-ex-base");
						var exn = document.getElementById("svp-ref-ex-n");
						if (exb) exb.value = d.referral_example_base_toman != null ? String(d.referral_example_base_toman) : "";
						if (exn) exn.value = d.referral_example_invite_count != null ? String(d.referral_example_invite_count) : "";
						if (rq) rq.checked = !!d.referral_require_approved_referrer;
						if (tg) tg.value = d.telegram_bot_username || "";
						if (bl) bl.value = d.bale_bot_username || "";
					}
				});
				return;
			}
			if (op === "referral_save") {
				var en2 = document.getElementById("svp-ref-en");
				var pct2 = document.getElementById("svp-ref-pct");
				var mn2 = document.getElementById("svp-ref-min");
				var exb2 = document.getElementById("svp-ref-ex-base");
				var exn2 = document.getElementById("svp-ref-ex-n");
				var rq2 = document.getElementById("svp-ref-req");
				var tg2 = document.getElementById("svp-ref-tg");
				var bl2 = document.getElementById("svp-ref-bl");
				var exr =
					"referral_enabled=" +
					encodeURIComponent(en2 && en2.checked ? "1" : "") +
					"&referral_percent=" +
					encodeURIComponent(pct2 ? pct2.value : "") +
					"&referral_min_payout_base=" +
					encodeURIComponent(mn2 ? mn2.value : "") +
					"&referral_example_base_toman=" +
					encodeURIComponent(exb2 ? exb2.value : "") +
					"&referral_example_invite_count=" +
					encodeURIComponent(exn2 ? exn2.value : "") +
					"&referral_require_approved_referrer=" +
					encodeURIComponent(rq2 && rq2.checked ? "1" : "") +
					"&telegram_bot_username=" +
					encodeURIComponent(tg2 ? tg2.value : "") +
					"&bale_bot_username=" +
					encodeURIComponent(bl2 ? bl2.value : "");
				post("referral_save", exr).then(function (j) {
					show("svp-adm-ref", j.success ? j.data : j);
				});
				return;
			}
			if (op === "discount_list") {
				post("discount_list", "").then(function (j) {
					show("svp-adm-disc", j.success ? j.data : j);
				});
				return;
			}
			if (op === "discount_save") {
				var did = document.getElementById("svp-disc-id");
				var dcode = document.getElementById("svp-disc-code");
				var dtype = document.getElementById("svp-disc-type");
				var dval = document.getElementById("svp-disc-value");
				var dmax = document.getElementById("svp-disc-max");
				var dfrom = document.getElementById("svp-disc-from");
				var duntil = document.getElementById("svp-disc-until");
				var dmin = document.getElementById("svp-disc-min");
				var dmaxord = document.getElementById("svp-disc-max-order");
				var dmaxdisc = document.getElementById("svp-disc-max-disc");
				var dplans = document.getElementById("svp-disc-plans");
				var duser = document.getElementById("svp-disc-user");
				var dnew = document.getElementById("svp-disc-allow-new");
				var drenew = document.getElementById("svp-disc-allow-renew");
				var dvol = document.getElementById("svp-disc-allow-vol");
				var dusers = document.getElementById("svp-disc-allow-users");
				var dact = document.getElementById("svp-disc-active");
				post(
					"discount_save",
					"discount_id=" +
						encodeURIComponent(did ? did.value : "0") +
						"&discount_code=" +
						encodeURIComponent(dcode ? dcode.value : "") +
						"&discount_type=" +
						encodeURIComponent(dtype ? dtype.value : "percent") +
						"&discount_value=" +
						encodeURIComponent(dval ? dval.value : "") +
						"&discount_max_uses=" +
						encodeURIComponent(dmax ? dmax.value : "") +
						"&discount_valid_from=" +
						encodeURIComponent(dfrom ? dfrom.value : "") +
						"&discount_valid_until=" +
						encodeURIComponent(duntil ? duntil.value : "") +
						"&discount_min_order=" +
						encodeURIComponent(dmin ? dmin.value : "") +
						"&discount_max_order=" +
						encodeURIComponent(dmaxord ? dmaxord.value : "") +
						"&discount_max_discount=" +
						encodeURIComponent(dmaxdisc ? dmaxdisc.value : "") +
						"&discount_plan_ids=" +
						encodeURIComponent(dplans ? dplans.value : "") +
						"&discount_restricted_user_id=" +
						encodeURIComponent(duser ? duser.value : "0") +
						"&discount_allow_new=" +
						encodeURIComponent(dnew && dnew.checked ? "1" : "") +
						"&discount_allow_renew=" +
						encodeURIComponent(drenew && drenew.checked ? "1" : "") +
						"&discount_allow_vol=" +
						encodeURIComponent(dvol && dvol.checked ? "1" : "") +
						"&discount_allow_users=" +
						encodeURIComponent(dusers && dusers.checked ? "1" : "") +
						"&discount_active=" +
						encodeURIComponent(dact && dact.checked ? "1" : "")
				).then(function (j) {
					show("svp-adm-disc", j.success ? j.data : j);
				});
				return;
			}
			if (op === "discount_delete") {
				var did = document.getElementById("svp-disc-del-id");
				post(
					"discount_delete",
					"discount_id=" + encodeURIComponent(did ? did.value : "")
				).then(function (j) {
					show("svp-adm-disc", j.success ? j.data : j);
				});
				return;
			}
		},
		false
	);

	applyPortalI18n(document);
})();
