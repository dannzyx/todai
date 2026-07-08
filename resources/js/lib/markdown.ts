/**
 * Minimal, dependency-free Markdown renderer for chat output.
 *
 * Safety: all HTML is escaped up front, so model output cannot inject markup.
 * Only a small, trusted subset of Markdown is then re-introduced as HTML —
 * paragraphs, headings, ordered/unordered lists, bold, italic, inline code and
 * links (http/https/mailto only). The result is safe to pass to `v-html`.
 */

const CODE_OPEN = '\uE000';
const CODE_CLOSE = '\uE001';

const escapeHtml = (text: string): string =>
    text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

/**
 * Apply inline formatting to a single (already HTML-escaped) line of text.
 */
const renderInline = (text: string): string => {
    // Stash inline code spans behind private-use sentinels so their contents
    // aren't touched by the other rules (and can't collide with real text).
    const codeSpans: string[] = [];
    let out = text.replace(/`([^`]+)`/g, (_match, code: string) => {
        codeSpans.push(code);

        return `${CODE_OPEN}${codeSpans.length - 1}${CODE_CLOSE}`;
    });

    out = out.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    out = out.replace(/(^|[^*])\*(?!\s)([^*]+?)\*/g, '$1<em>$2</em>');
    out = out.replace(/(^|[^\w_])_([^_]+)_/g, '$1<em>$2</em>');

    out = out.replace(
        /\[([^\]]+)\]\(([^)]+)\)/g,
        (_match, label: string, url: string) => {
            const safeUrl = /^(https?:|mailto:)/i.test(url) ? url : '#';

            return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${label}</a>`;
        },
    );

    return out.replace(
        new RegExp(`${CODE_OPEN}(\\d+)${CODE_CLOSE}`, 'g'),
        (_match, index: string) => `<code>${codeSpans[Number(index)]}</code>`,
    );
};

export const renderMarkdown = (source: string): string => {
    const lines = escapeHtml(source.replace(/\r\n/g, '\n')).split('\n');
    const blocks: string[] = [];
    let paragraph: string[] = [];
    let index = 0;

    const flushParagraph = (): void => {
        if (paragraph.length > 0) {
            blocks.push(`<p>${renderInline(paragraph.join(' '))}</p>`);
            paragraph = [];
        }
    };

    while (index < lines.length) {
        const trimmed = lines[index].trim();

        if (trimmed === '') {
            flushParagraph();
            index++;
            continue;
        }

        const heading = /^(#{1,6})\s+(.*)$/.exec(trimmed);

        if (heading) {
            flushParagraph();
            const level = heading[1].length;
            blocks.push(`<h${level}>${renderInline(heading[2])}</h${level}>`);
            index++;
            continue;
        }

        if (/^[-*]\s+/.test(trimmed)) {
            flushParagraph();
            const items: string[] = [];

            while (index < lines.length && /^[-*]\s+/.test(lines[index].trim())) {
                const item = lines[index].trim().replace(/^[-*]\s+/, '');
                items.push(`<li>${renderInline(item)}</li>`);
                index++;
            }

            blocks.push(`<ul>${items.join('')}</ul>`);
            continue;
        }

        if (/^\d+\.\s+/.test(trimmed)) {
            flushParagraph();
            const items: string[] = [];

            while (
                index < lines.length &&
                /^\d+\.\s+/.test(lines[index].trim())
            ) {
                const item = lines[index].trim().replace(/^\d+\.\s+/, '');
                items.push(`<li>${renderInline(item)}</li>`);
                index++;
            }

            blocks.push(`<ol>${items.join('')}</ol>`);
            continue;
        }

        paragraph.push(trimmed);
        index++;
    }

    flushParagraph();

    return blocks.join('');
};
