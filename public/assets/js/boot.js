/* 首屏标记：移动端导航按 html.js 决定"默认收起"，脚本没跑到就保持展开，菜单不会丢。
   外链而非内联：面板的 CSP 是 script-src 'self' https:，内联脚本会被拦掉。 */
document.documentElement.classList.add('js');
