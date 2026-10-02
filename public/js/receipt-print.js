(function() {
    const receiptPrinterState = {
        appName: 'NIKONET',
        billingDueDay: 27,
        paperWidthMm: 58,
        tenantId: null,
        tenantName: ''
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function configureReceiptPrinter(nextConfig = {}) {
        if (!nextConfig || typeof nextConfig !== 'object') return;
        if (Object.prototype.hasOwnProperty.call(nextConfig, 'appName')) {
            receiptPrinterState.appName = String(nextConfig.appName || '').trim() || 'NIKONET';
        }
        if (Object.prototype.hasOwnProperty.call(nextConfig, 'billingDueDay')) {
            const dueDay = Number(nextConfig.billingDueDay || 27);
            receiptPrinterState.billingDueDay = Number.isFinite(dueDay) ? Math.min(31, Math.max(1, Math.round(dueDay))) : 27;
        }
        if (Object.prototype.hasOwnProperty.call(nextConfig, 'paperWidthMm')) {
            const paperWidth = Number(nextConfig.paperWidthMm || 58);
            receiptPrinterState.paperWidthMm = Number.isFinite(paperWidth) ? Math.min(80, Math.max(48, Math.round(paperWidth))) : 58;
        }
        if (Object.prototype.hasOwnProperty.call(nextConfig, 'tenantId')) {
            const tenantId = Number(nextConfig.tenantId);
            receiptPrinterState.tenantId = Number.isFinite(tenantId) && tenantId > 0 ? Math.round(tenantId) : null;
        }
        if (Object.prototype.hasOwnProperty.call(nextConfig, 'tenantName')) {
            receiptPrinterState.tenantName = String(nextConfig.tenantName || '').trim();
        }
    }

    function getReceiptAppName(options = {}) {
        return escapeHtml(String(options.appName || receiptPrinterState.appName || 'NIKONET').trim() || 'NIKONET');
    }

    function getReceiptPaperWidth(options = {}) {
        const width = Number(options.paperWidthMm || receiptPrinterState.paperWidthMm || 58);
        return Number.isFinite(width) ? Math.min(80, Math.max(48, Math.round(width))) : 58;
    }

    function getReceiptTenantId(options = {}) {
        const tenantId = Number(options.tenantId || receiptPrinterState.tenantId || 0);
        return Number.isFinite(tenantId) && tenantId > 0 ? Math.round(tenantId) : null;
    }

    function getReceiptTenantName(options = {}) {
        return String(options.tenantName || receiptPrinterState.tenantName || receiptPrinterState.appName || '').trim();
    }

    function formatReceiptAmount(value) {
        const amount = Number(value || 0);
        return `Rp ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(amount)}`;
    }

    function sortPrintableEntries(items = []) {
        return (items || []).slice().sort((a, b) => {
            const left = String(a?.nama || a?.name || a?.username || '').trim();
            const right = String(b?.nama || b?.name || b?.username || '').trim();
            return left.localeCompare(right, 'id', { sensitivity: 'base' });
        });
    }

    function buildReceiptInvoiceCode(source = {}, options = {}) {
        const tenantId = getReceiptTenantId(options) || 'X';
        const billingId = Number(source?.id || 0);
        const userId = Number(source?.user_id || source?.id || 0);
        if (billingId > 0 && source?.user_id) {
            return `INV-${tenantId}-${billingId}`;
        }
        return `INV-${tenantId}-U${userId || 'X'}`;
    }

    function buildReceiptQrPayload(source = {}, options = {}) {
        const userId = Number(source?.user_id || source?.id || 0) || null;

        return JSON.stringify({
            app: 'rtrwnet',
            version: 3,
            type: 'client_lookup',
            user_id: userId,
            tenant_id: getReceiptTenantId(options),
            invoice_code: buildReceiptInvoiceCode(source, options)
        });
    }

    function buildReceiptQrImageUrl(payload) {
        const params = new URLSearchParams({
            size: '280x280',
            qzone: '4',
            ecc: 'L',
            format: 'svg',
            data: payload
        });
        return `https://api.qrserver.com/v1/create-qr-code/?${params.toString()}`;
    }

    function buildPaymentsReceiptHtml(users = [], billings = [], options = {}) {
        const appName = getReceiptAppName(options);
        const now = new Date();
        const receiptDate = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(now);
        const dueDay = options.billingDueDay || receiptPrinterState.billingDueDay || 27;
        const sortedBillings = sortPrintableEntries(billings || []);
        const sortedUsers = sortPrintableEntries(users || []);
        const printableUserOrder = new Map();

        sortedUsers.forEach((user, index) => {
            const userId = Number(user?.id || 0);
            const username = String(user?.username || '').trim().toLowerCase();
            const name = String(user?.nama || user?.name || '').trim().toLowerCase();

            if (userId > 0) {
                printableUserOrder.set(`id:${userId}`, index + 1);
            }
            if (username !== '') {
                printableUserOrder.set(`username:${username}`, index + 1);
            }
            if (name !== '') {
                printableUserOrder.set(`name:${name}`, index + 1);
            }
        });

        const printableRows = sortedBillings.length
            ? sortedBillings.map((billing, index) => {
                const userId = Number(billing?.user_id || billing?.id || 0);
                const username = String(billing?.username || '').trim().toLowerCase();
                const name = String(billing?.nama || billing?.name || '').trim().toLowerCase();
                const sequence = printableUserOrder.get(`id:${userId}`)
                    || printableUserOrder.get(`username:${username}`)
                    || printableUserOrder.get(`name:${name}`)
                    || (index + 1);

                return { source: billing, index, sequence };
            })
            : sortedUsers.map((user, index) => ({ source: user, index }));

        const receiptBlocks = printableRows.map(({ source, index, sequence }) => {
            const amountLabel = formatReceiptAmount(source?.jumlah);
            const qrPayload = buildReceiptQrPayload(source, options);
            const qrImageUrl = buildReceiptQrImageUrl(qrPayload);
            const invoiceCode = buildReceiptInvoiceCode(source, options);

            return '<section class="receipt-sheet">' +
                '<div class="receipt-brand">' + appName + '</div>' +
                '<div class="receipt-divider"></div>' +
                '<div class="receipt-title">BUKTI PEMBAYARAN</div>' +
                '<div class="receipt-sequence">NO. ' + (sequence || (index + 1)) + '</div>' +
                '<div class="receipt-row"><div class="receipt-label">NAMA</div><div class="receipt-value">' + escapeHtml(source?.nama || source?.name || '-') + '</div></div>' +
                '<div class="receipt-row"><div class="receipt-label">BAYAR</div><div class="receipt-value">' + amountLabel + '</div></div>' +
                '<div class="receipt-row"><div class="receipt-label">TASNGGAL</div><div class="receipt-value">' + receiptDate + '</div></div>' +
                '<div class="receipt-qr-wrap">' +
                '<img class="receipt-qr-image" src="' + escapeHtml(qrImageUrl) + '" alt="QR Nota Pembayaran">' +
                '<div class="receipt-qr-caption">Scan QR ini di aplikasi Android untuk membuka data pelanggan dan tagihan.</div>' +
                '<div class="receipt-qr-code">' + escapeHtml(invoiceCode) + '</div>' +
                '</div>' +
                '<div class="receipt-divider receipt-divider-spaced"></div>' +
                '<div class="receipt-note">Demi Kenyamanan Bersama <br> Silahkan Lakukan Pembayaran Setiap Tanggal ' + dueDay + '<br>Terimakasih telah melakukan pembayaran tepat waktu.</div>' +
                '</section>';
        }).join('');

        return buildThermalWrapper(receiptBlocks, options);
    }

    function buildVoucherPrintHtml(vouchers = [], options = {}) {
        const appName = getReceiptAppName(options);
        const voucherBlocks = (vouchers || []).map(v => {
            let infoHtml = '';
            if (v.quota_display || v.price) {
                infoHtml += '<div style="display:flex; justify-content:center; align-items:center; gap:12px; width:100%; font-size:11px; margin-top:2px; font-weight:bold;">';
                infoHtml += '<span>' + (v.price ? escapeHtml(v.price) : '') + '</span>';
                infoHtml += '<span>' + (v.quota_display ? escapeHtml(v.quota_display) : '') + '</span>';
                infoHtml += '</div>';
            }

            return '<section class="receipt-sheet receipt-sheet-voucher">' +
                '<div class="receipt-brand">' + appName + '</div>' +
                '<div class="receipt-divider"></div>' +
                infoHtml +
                '<div class="receipt-title" style="margin-top:5px; margin-bottom:2px;">VOUCHER HOTSPOT</div>' +
                '<div class="receipt-row"><div class="receipt-value" style="font-size:22px; border:1px dashed #000; padding:6px;">' + escapeHtml(v.username || '') + '</div></div>' +
                '<div style="display:flex; justify-content:space-between; width:100%; font-size:10px; margin-top:2px; padding:0 4px;">' +
                '<span>ID: ' + escapeHtml(v.id_voucher || '-') + '</span>' +
                (v.shop_id ? '<span>TOKO: ' + escapeHtml(v.shop_id) + '</span>' : '') +
                '</div>' +
                '<div class="receipt-note" style="font-size:9px; margin-top:4px;">Silahkan masukkan kode <br>voucher di atas.<br>Terima kasih!</div>' +
                '<div class="receipt-note" style="font-size:9px;"><i>~ ' + appName + ' Voucher Access ~</i></div>' +
                '</section>';
        }).join('');

        return buildThermalWrapper(voucherBlocks, options);
    }

    function buildNameTemplateHtml(users = [], options = {}) {
        const appName = getReceiptAppName(options);
        const printableUsers = sortPrintableEntries(Array.isArray(users) ? users : []);
        const rowHtml = printableUsers.map((user, index) => {
            const name = escapeHtml(user?.nama || user?.name || '-');

            return '<tr>' +
                '<td class="name-list-no">' + (index + 1) + '</td>' +
                '<td class="name-list-name">' + name + '</td>' +
                '<td class="name-list-check">[&nbsp;&nbsp;]</td>' +
                '</tr>';
        }).join('');

        const contentHtml = '<section class="receipt-sheet receipt-sheet-name">' +
            '<div class="receipt-brand">' + appName + '</div>' +
            '<div class="receipt-divider"></div>' +
            '<div class="receipt-title">DAFTAR NAMA</div>' +
            '<table class="name-sheet-table">' +
            '<thead><tr><th class="name-list-no">NO</th><th>NAMA</th><th class="name-list-check">CEK</th></tr></thead>' +
            '<tbody>' + rowHtml + '</tbody>' +
            '</table>' +
            '</section>';

        return buildThermalWrapper(contentHtml, {
            ...options,
            extraStyles: '.receipt-sheet-name { align-items: stretch; text-align: left; padding-top: 3mm; }' +
                '.name-sheet-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 1mm; }' +
                '.name-sheet-table th, .name-sheet-table td { border: 1px solid #000; padding: 2mm 1.2mm; font-size: 20px; vertical-align: middle; }' +
                '.name-sheet-table th { text-align: center; }' +
                '.name-list-no { width: 10mm; text-align: center; }' +
                '.name-list-check { width: 16mm; text-align: center; white-space: nowrap; }' +
                '.name-list-name { word-break: break-word; }'
        });
    }

    function buildThermalWrapper(contentHtml, options = {}) {
        const paperWidth = getReceiptPaperWidth(options);
        return '<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Print</title>' +
            '<style>' +
            '@page { size: ' + paperWidth + 'mm auto; margin: 0; }' +
            '* { box-sizing: border-box; }' +
            'body { margin: 0; padding: 0; width: ' + paperWidth + 'mm; font-family: "Courier New", monospace; font-weight: 900; }' +
            '.receipt-sheet { width: ' + paperWidth + 'mm; padding: 4mm 1.5mm; display: flex; flex-direction: column; align-items: center; text-align: center; break-after: page; page-break-after: always; }' +
            '.receipt-sheet-voucher { padding: 2.5mm 1.5mm 1.5mm; }' +
            '.receipt-brand { font-size: 20px; margin-bottom: 2mm; text-transform: uppercase; }' +
            '.receipt-divider { width: 100%; border-top: 1px solid #000; margin: 1.5mm 0 2mm; }' +
            '.receipt-title { font-size: 15px; margin-bottom: 2mm; text-transform: uppercase; }' +
            '.receipt-sequence { font-size: 11px; margin-bottom: 2mm; }' +
            '.receipt-row { width: 100%; margin-bottom: 2mm; }' +
            '.receipt-label { font-size: 12px; margin-bottom: 1mm; }' +
            '.receipt-value { font-size: 15px; word-break: break-all; }' +
            '.receipt-qr-wrap { width: 100%; display: flex; flex-direction: column; align-items: center; margin: 3.5mm 0 2.5mm; padding: 1.5mm 0 0; }' +
            '.receipt-qr-image { width: 52mm; height: 52mm; display: block; margin: 0 auto 2.2mm; padding: 2.4mm; background: #fff; }' +
            '.receipt-qr-caption { font-size: 8px; line-height: 1.25; max-width: 92%; margin-top: 0.2mm; }' +
            '.receipt-qr-code { font-size: 10px; margin-top: 1.6mm; letter-spacing: 0.4px; word-break: break-all; }' +
            '.receipt-note { font-size: 9px; margin-top: 1.6mm; line-height: 1.15; }' +
            (options.extraStyles || '') +
            '@media screen { body { background: #eee; display: flex; flex-direction: column; align-items: center; padding: 20px; width: 100%; } .receipt-sheet { background: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 10px; } }' +
            '</style></head><body><main class="print-wrap">' + contentHtml + '</main>' +
            '<script>window.onload = function() { window.print(); setTimeout(() => { window.close(); }, 500); };<\/script>' +
            '</body></html>';
    }

    function printReceipts(users = [], billings = [], options = {}) {
        const paperWidth = getReceiptPaperWidth(options);
        const popupWidth = paperWidth >= 80 ? 700 : 560;
        const win = window.open('', '_blank', 'width=' + popupWidth + ',height=700');
        win.document.write(buildPaymentsReceiptHtml(users, billings, options));
        win.document.close();
    }

    function printVouchers(vouchers = [], options = {}) {
        const paperWidth = getReceiptPaperWidth(options);
        const popupWidth = paperWidth >= 80 ? 700 : 560;
        const win = window.open('', '_blank', 'width=' + popupWidth + ',height=700');
        win.document.write(buildVoucherPrintHtml(vouchers, options));
        win.document.close();
    }

    function printNameTemplate(users = [], options = {}) {
        const win = window.open('', '_blank', 'width=900,height=700');
        win.document.write(buildNameTemplateHtml(users, options));
        win.document.close();
    }

    window.receiptPrinter = {
        configure: configureReceiptPrinter,
        printReceipts,
        printVouchers,
        printNameTemplate
    };
})();
