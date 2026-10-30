const rupiah = (value) => {
    const number = Number(value || 0);
    const sign = number < 0 ? '-' : '';
    return sign + 'Rp' + Math.abs(Math.round(number)).toLocaleString('id-ID');
};

document.addEventListener('alpine:init', () => {
    Alpine.store('toasts', {
        items: [],
        counter: 0,
        push(type, message) {
            const id = ++this.counter;
            this.items.push({ id, type: type || 'info', message });
            window.setTimeout(() => this.remove(id), 4800);
        },
        remove(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });

    Alpine.store('dialog', {
        open: false,
        title: 'Konfirmasi',
        message: '',
        url: '',
        method: 'POST',
        confirmLabel: 'Ya, Lanjutkan',
        ask(options) {
            this.title = options.title || 'Konfirmasi';
            this.message = options.message || 'Apakah kamu yakin ingin melanjutkan?';
            this.url = options.url || '';
            this.method = options.method || 'POST';
            this.confirmLabel = options.confirmLabel || 'Ya, Lanjutkan';
            this.open = true;
        },
        close() {
            this.open = false;
        },
    });
});

window.toast = (type, message) => {
    if (window.Alpine && Alpine.store('toasts')) {
        Alpine.store('toasts').push(type, message);
    }
};

let receiptUid = 0;

window.receiptEditor = function (config = {}) {
    const blankItem = () => ({ uid: ++receiptUid, id: null, name: '', quantity: 1, unit_price: 0, total_price: null });

    const initialItems = (config.items || []).map((item) => ({
        uid: ++receiptUid,
        id: item.id ?? null,
        name: item.name || '',
        quantity: Number(item.quantity || 1),
        unit_price: Number(item.unit_price || 0),
        total_price: item.total_price === undefined || item.total_price === null ? null : Number(item.total_price),
    }));

    return {
        items: initialItems.length ? initialItems : [blankItem()],
        receiptTotal: Number(config.receiptTotal || 0),
        tax: Number(config.tax || 0),
        service: Number(config.service || 0),
        discount: Number(config.discount || 0),
        other: Number(config.other || 0),
        taxIncluded: Boolean(config.taxIncluded),

        itemIssues(item) {
            const issues = [];
            const quantity = Number(item.quantity || 0);
            const unitPrice = Number(item.unit_price || 0);
            const computed = Math.round(quantity * unitPrice);
            const scanned = item.total_price;

            if (!String(item.name || '').trim()) {
                issues.push('Nama item masih kosong.');
            }

            if (unitPrice <= 0) {
                issues.push('Harga satuan masih Rp0.');
            }

            if (scanned !== null && scanned !== undefined && Number(scanned) > 0 && computed !== Number(scanned)) {
                issues.push('Qty \u00d7 harga satuan (' + rupiah(computed) + ') tidak sama dengan total yang terbaca di struk (' + rupiah(scanned) + ').');
            }

            if (quantity >= 100) {
                issues.push('Kuantitas ' + quantity + ' terlihat tidak wajar \u2014 kemungkinan salah baca satuan (Kg/Pcs).');
            }

            return issues;
        },
        itemIssueCount() {
            return this.items.filter((item) => this.itemIssues(item).length > 0).length;
        },
        computedTotal() {
            return this.subtotal()
                + (this.taxIncluded ? 0 : Number(this.tax || 0))
                + Number(this.service || 0)
                + Number(this.other || 0)
                - Number(this.discount || 0);
        },
        totalDifference() {
            return this.computedTotal() - this.receiptTotal;
        },
        hasTotalWarning() {
            return this.receiptTotal > 0 && Math.abs(this.totalDifference()) > 100;
        },
        forgetScannedTotal(item) {
            item.total_price = null;
        },

        addItem() {
            this.items.push(blankItem());
        },
        removeItem(index) {
            this.items.splice(index, 1);
            if (this.items.length === 0) {
                this.addItem();
            }
        },
        subtotal() {
            return this.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0);
        },
        grandTotal() {
            const subtotal = this.subtotal();
            const tax = this.taxIncluded ? 0 : Number(this.tax || 0);
            return Math.max(0, subtotal + tax + Number(this.service || 0) + Number(this.other || 0) - Number(this.discount || 0));
        },
        lineTotal(item) {
            return Number(item.quantity || 0) * Number(item.unit_price || 0);
        },
        money(value) {
            return rupiah(value);
        },
    };
};

let participantUid = 1000;

window.participantsEditor = function (config = {}) {
    const palette = ['#16A34A', '#2563EB', '#9333EA', '#EA580C', '#0891B2', '#DB2777', '#4F46E5', '#D97706'];
    const ownerName = String(config.defaultName || '').trim().toLowerCase();

    const participants = (config.participants || []).map((participant) => ({
        uid: ++participantUid,
        id: participant.id ?? null,
        name: participant.name || '',
        is_current_user: ownerName !== '' && String(participant.name || '').trim().toLowerCase() === ownerName,
        color: participant.color || null,
        initials: participant.initials || null,
    }));

    if (participants.length && !participants.some((participant) => participant.is_current_user)) {
        participants[0].is_current_user = true;
    }

    return {
        participants: participants.length ? participants : [{ uid: ++participantUid, id: null, name: config.defaultName || '', is_current_user: true, color: palette[0] }],
        newName: '',
        add() {
            const name = this.newName.trim();
            if (!name) {
                return;
            }
            this.participants.push({
                uid: ++participantUid,
                id: null,
                name,
                is_current_user: false,
                color: palette[this.participants.length % palette.length],
            });
            this.newName = '';
        },
        remove(index) {
            if (this.participants.length <= 1) {
                window.toast('warning', 'Minimal satu peserta harus ada.');
                return;
            }
            const wasCurrent = this.participants[index].is_current_user;
            this.participants.splice(index, 1);
            if (wasCurrent) {
                this.participants[0].is_current_user = true;
            }
        },
        initials(name) {
            return (name || '?')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map((part) => part.charAt(0).toUpperCase())
                .join('') || '?';
        },
    };
};

window.allocationWizard = function (config = {}) {
    const items = (config.items || []).map((item) => ({
        id: Number(item.id),
        name: item.name || '',
        quantity: Number(item.quantity || 1),
        unit_price: Number(item.unit_price || 0),
        total_price: Number(item.total_price || 0),
    }));

    const participants = (config.participants || []).map((participant) => ({
        id: Number(participant.id),
        name: participant.name || '',
        initials: participant.initials || '',
        color: participant.color || '#16A34A',
    }));

    const initial = config.allocations || {};
    const store = {};

    items.forEach((item) => {
        const saved = initial[item.id] || initial[String(item.id)] || null;
        const portions = {};

        if (saved && Array.isArray(saved.portion_assignments)) {
            saved.portion_assignments.forEach((portion) => {
                portions[Number(portion.participant_id)] = Number(portion.quantity || 0);
            });
        }

        store[item.id] = {
            type: saved && saved.type ? saved.type : 'shared_equal',
            single: saved && saved.single_participant_id ? Number(saved.single_participant_id) : (participants[0]?.id ?? null),
            shared: saved && Array.isArray(saved.shared_participant_ids) && saved.shared_participant_ids.length
                ? saved.shared_participant_ids.map(Number)
                : participants.map((participant) => participant.id),
            portions,
        };
    });

    return {
        items,
        participants,
        allocations: store,
        tax: Number(config.tax || 0),
        service: Number(config.service || 0),
        discount: Number(config.discount || 0),
        other: Number(config.other || 0),
        taxRule: config.taxRule || 'proportional',
        serviceRule: config.serviceRule || 'proportional',
        discountRule: config.discountRule || 'proportional',
        roundingMethod: config.rounding || 'nearest_100',

        setType(item, type) {
            const allocation = this.allocations[item.id];
            allocation.type = type;

            if (type === 'single' && !allocation.single) {
                allocation.single = this.participants[0]?.id ?? null;
            }

            if (type === 'shared_equal' && allocation.shared.length === 0) {
                allocation.shared = this.participants.map((participant) => participant.id);
            }

            if (type === 'by_portion') {
                Object.keys(allocation.portions).forEach((key) => {
                    allocation.portions[key] = allocation.portions[key] || 0;
                });
            }
        },

        toggleShared(item, participantId) {
            const allocation = this.allocations[item.id];
            const index = allocation.shared.indexOf(participantId);

            if (index >= 0) {
                allocation.shared.splice(index, 1);
            } else {
                allocation.shared.push(participantId);
            }
        },

        isShared(item, participantId) {
            return this.allocations[item.id].shared.includes(participantId);
        },

        setSingle(item, participantId) {
            this.allocations[item.id].single = participantId;
        },

        portion(item, participantId) {
            return Number(this.allocations[item.id].portions[participantId] || 0);
        },

        assignedQty(item) {
            return Object.values(this.allocations[item.id].portions).reduce((sum, qty) => sum + Number(qty || 0), 0);
        },

        changePortion(item, participantId, delta) {
            const allocation = this.allocations[item.id];
            const current = Number(allocation.portions[participantId] || 0);
            const assigned = this.assignedQty(item);
            const available = item.quantity - (assigned - current);
            const next = Math.min(Math.max(current + delta, 0), Math.max(available, 0));
            allocation.portions[participantId] = next;
        },

        isAllocated(item) {
            const allocation = this.allocations[item.id];

            if (allocation.type === 'single') {
                return Boolean(allocation.single);
            }

            if (allocation.type === 'shared_equal') {
                return allocation.shared.length > 0;
            }

            return this.assignedQty(item) === Number(item.quantity);
        },

        completedCount() {
            return this.items.filter((item) => this.isAllocated(item)).length;
        },

        sharePreview(item) {
            const allocation = this.allocations[item.id];

            if (allocation.type === 'shared_equal') {
                return allocation.shared.length ? Math.round(item.total_price / allocation.shared.length) : 0;
            }

            return 0;
        },

        applyEqualAll() {
            this.items.forEach((item) => {
                this.allocations[item.id].type = 'shared_equal';
                this.allocations[item.id].shared = this.participants.map((participant) => participant.id);
            });
        },

        resetAll() {
            this.items.forEach((item) => {
                this.allocations[item.id] = {
                    type: 'shared_equal',
                    single: this.participants[0]?.id ?? null,
                    shared: [],
                    portions: {},
                };
            });
        },

        payload() {
            const result = {};

            this.items.forEach((item) => {
                const allocation = this.allocations[item.id];

                result[item.id] = {
                    type: allocation.type,
                    single_participant_id: allocation.type === 'single' ? allocation.single : null,
                    shared_participant_ids: allocation.type === 'shared_equal' ? allocation.shared : [],
                    portion_assignments: allocation.type === 'by_portion'
                        ? Object.entries(allocation.portions)
                            .map(([participantId, quantity]) => ({ participant_id: Number(participantId), quantity: Number(quantity) }))
                            .filter((portion) => portion.quantity > 0)
                        : [],
                };
            });

            return JSON.stringify(result);
        },

        round(value) {
            switch (this.roundingMethod) {
                case 'nearest_500':
                    return Math.round(value / 500) * 500;
                case 'nearest_1000':
                    return Math.round(value / 1000) * 1000;
                case 'none':
                    return Math.round(value);
                default:
                    return Math.round(value / 100) * 100;
            }
        },

        // Mirrors SplitBillCalculator::calculate() so the live preview matches
        // what the server will persist on the summary step.
        perPersonTotals() {
            const totals = {};

            this.participants.forEach((participant) => {
                totals[participant.id] = {
                    participant,
                    items_subtotal: 0,
                    discount_share: 0,
                    tax_share: 0,
                    service_share: 0,
                    other_fees_share: 0,
                    final_total: 0,
                };
            });

            this.items.forEach((item) => {
                const allocation = this.allocations[item.id];

                if (!allocation) {
                    return;
                }

                if (allocation.type === 'single') {
                    if (allocation.single && totals[allocation.single]) {
                        totals[allocation.single].items_subtotal += item.total_price;
                    }

                    return;
                }

                if (allocation.type === 'shared_equal') {
                    const ids = allocation.shared.filter((id) => totals[id]);

                    if (ids.length) {
                        const perPerson = Math.round(item.total_price / ids.length);
                        let remainder = item.total_price - perPerson * ids.length;

                        ids.forEach((id, index) => {
                            totals[id].items_subtotal += perPerson + (index === 0 ? remainder : 0);
                        });
                    }

                    return;
                }

                if (allocation.type === 'by_portion') {
                    Object.entries(allocation.portions).forEach(([participantId, quantity]) => {
                        const id = Number(participantId);

                        if (totals[id] && Number(quantity) > 0) {
                            totals[id].items_subtotal += Math.round(Number(quantity) * item.unit_price);
                        }
                    });
                }
            });

            const list = Object.values(totals);
            const totalSubtotal = list.reduce((sum, entry) => sum + entry.items_subtotal, 0);
            const count = Math.max(1, list.length);

            const distribute = (field, amount, rule) => {
                if (amount <= 0) {
                    list.forEach((entry) => { entry[field] = 0; });

                    return;
                }

                if (rule === 'proportional' && totalSubtotal > 0) {
                    let allocated = 0;

                    list.forEach((entry, index) => {
                        if (index === list.length - 1) {
                            entry[field] = Math.max(0, amount - allocated);
                        } else {
                            const part = Math.round((entry.items_subtotal / totalSubtotal) * amount);
                            entry[field] = part;
                            allocated += part;
                        }
                    });

                    return;
                }

                if (rule === 'equal') {
                    const perPerson = Math.floor(amount / count);
                    let remainder = amount - perPerson * count;

                    list.forEach((entry) => {
                        entry[field] = perPerson + (remainder > 0 ? 1 : 0);
                        remainder = remainder > 0 ? remainder - 1 : 0;
                    });

                    return;
                }

                list.forEach((entry) => { entry[field] = 0; });
            };

            distribute('discount_share', this.discount, this.discountRule);
            distribute('tax_share', this.tax, this.taxRule);
            distribute('service_share', this.service, this.serviceRule);
            distribute('other_fees_share', this.other, 'equal');

            list.forEach((entry) => {
                const raw = entry.items_subtotal - entry.discount_share
                    + entry.tax_share + entry.service_share + entry.other_fees_share;

                entry.final_total = Math.max(0, this.round(raw));
            });

            return list;
        },

        allocatedTotal() {
            return this.perPersonTotals().reduce((sum, entry) => sum + entry.final_total, 0);
        },

        money(value) {
            return rupiah(value);
        },
    };
};

const paintIcons = () => {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
        window.lucide.createIcons();
    }
};

let iconPaintScheduled = false;
const scheduleIconPaint = () => {
    if (iconPaintScheduled) {
        return;
    }

    iconPaintScheduled = true;

    window.requestAnimationFrame(() => {
        iconPaintScheduled = false;
        paintIcons();
    });
};

document.addEventListener('DOMContentLoaded', () => {
    paintIcons();
    window.setTimeout(paintIcons, 300);

    // Ikon `data-lucide` yang dibuat belakangan oleh Alpine (x-if / x-for, toast)
    // ikut digambar tanpa perlu interaksi ulang.
    new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (node.nodeType === 1 && (node.matches('[data-lucide]') || node.querySelector('[data-lucide]'))) {
                    scheduleIconPaint();

                    return;
                }
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
});

document.addEventListener('alpine:initialized', () => {
    paintIcons();
    window.setTimeout(paintIcons, 120);
});

document.addEventListener('click', async (event) => {
    const trigger = event.target.closest('[data-copy]');

    if (trigger) {
        const value = trigger.getAttribute('data-copy');

        try {
            await navigator.clipboard.writeText(value);
            window.toast('success', trigger.getAttribute('data-copy-message') || 'Berhasil disalin ke clipboard.');
        } catch (error) {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            textarea.remove();
            window.toast('success', trigger.getAttribute('data-copy-message') || 'Berhasil disalin ke clipboard.');
        }
    }
});

// Konfirmasi destruktif lewat data-attribute. Dipakai sebagai pengganti
// @js(...) di dalam atribut @click (yang memutus atribut HTML).
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm-delete]');

    if (! trigger) {
        return;
    }

    event.preventDefault();

    const dialog = window.Alpine && window.Alpine.store('dialog');

    if (! dialog) {
        if (window.confirm(trigger.dataset.confirmMessage || 'Yakin ingin menghapus?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = trigger.dataset.confirmUrl || '';

            const token = document.querySelector('meta[name="csrf-token"]');
            const method = document.createElement('input');

            method.type = 'hidden';
            method.name = '_method';
            method.value = trigger.dataset.confirmMethod || 'DELETE';

            const csrf = document.createElement('input');

            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = token ? token.getAttribute('content') : '';

            form.appendChild(csrf);
            form.appendChild(method);
            document.body.appendChild(form);
            form.submit();
        }

        return;
    }

    dialog.ask({
        title: trigger.dataset.confirmTitle || 'Konfirmasi',
        message: trigger.dataset.confirmMessage || 'Apakah kamu yakin ingin melanjutkan?',
        url: trigger.dataset.confirmUrl || '',
        method: trigger.dataset.confirmMethod || 'DELETE',
        confirmLabel: trigger.dataset.confirmLabel || 'Hapus',
    });
});

window.addEventListener('pageshow', () => {
    paintIcons();
});
