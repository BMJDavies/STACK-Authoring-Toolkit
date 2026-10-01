---
name: jsxgraph
description: JSXGraph figure templates for STACK questions. How to embed a board, common figure types, board sizing, and the pitfalls when mixing JSXGraph with STACK inputs.
---

# JSXGraph in STACK questions

> Content rules (when to use it, accessibility, label overlap) are in
> `style-guide.md`. This skill covers implementation.

JSXGraph is the only figure library used in this project. Every interactive
figure goes through it.

## Embedding a board

**Always use `[[jsxgraph]]` blocks, never raw `<script>` tags.** STACK strips
`<script>` for security, so a raw script gives you a blank space where the
figure should be.

**Comments: `//` only, never `/* ... */`.** STACK's CASText validator rejects
question text containing block comments as "no longer supported". Moodle import
still tolerates them, but the style is deprecated.

```html
[[jsxgraph width="400px" height="280px"]]
(function() {
  var b = JXG.JSXGraph.initBoard(divid, {
    boundingbox: [xmin, ymax, xmax, ymin],
    axis: true, grid: false,
    showNavigation: false, showCopyright: false
  });
  // create elements here
})();
[[/jsxgraph]]
```

Differences from plain HTML:

- Use `divid`, which STACK provides. Never a hardcoded id.
- Width and height go on the `[[jsxgraph]]` tag, not on a `<div>`.
- No `<div>` or `<script>` tags of your own. STACK generates them.

**The IIFE is mandatory.** Two JSXGraph blocks on the same quiz page share
global scope, so without the wrapper their `b` variables collide and one figure
silently overwrites the other.

Accessibility wrapper, required by the style guide:

```html
<div style="text-align:center;" role="img" aria-label="Description of figure">
[[jsxgraph width="400px" height="280px"]]
...
[[/jsxgraph]]
</div>
```

## Board sizing

- Width: 260 to 320px for an inline figure, 400px standalone
- Height: chosen so the data fits, typically 180 to 220px
- `boundingbox: [xmin, ymax, xmax, ymin]`. Note that Y is inverted: top-left
  first, then bottom-right.
- Leave about 10% padding around the data. Set a tight bounding box rather than
  relying on auto-zoom.

## Label positioning

Labels must not sit on top of curves. Use `anchorX`, `anchorY` and `offset`:

```javascript
b.create('point', [x, y], {
  size: 4, color: '#cc0000', fixed: true,
  name: 'vertex',
  label: { offset: [10, -15], anchorX: 'left' }
});
```

Check labels at the board's actual dimensions, not at some imagined size. For
points near an intersection, use larger offsets. For free text, use
`b.create('text', [x, y, 'label'], { anchorX: 'middle', fixed: true })` with
explicit coordinates away from the curve.

## Figure-only slots: skinny STACK

`[[jsxgraph]]` is processed by STACK's CASText filter, which only runs inside
`type="stack"` questions. In a `type="description"` it renders as literal text
on the page, which looks like a bug to students because it is one.

For a slot that is only a figure and prose (a worked example, a key-point
summary, a header with a diagram), use a skinny STACK question: `type="stack"`
with no `<input>`, no `<prt>` and no `<qtest>`.

```xml
<question type="stack">
  <name><text>Section X: Example, short title</text></name>
  <questiontext format="html">
    <text><![CDATA[
<hr>
<h4 class="tb-example">Example</h4>
<p>Question prose…</p>
<h4 class="tb-solution">Solution</h4>
<p>Worked solution prose…</p>
<div style="text-align:center;" role="img" aria-label="Description of figure">
[[jsxgraph width="400px" height="300px"]]
(function() { // board code
})();
[[/jsxgraph]]
</div>
    ]]></text>
  </questiontext>
  <generalfeedback format="html"><text></text></generalfeedback>
  <defaultgrade>0</defaultgrade>
  <penalty>0</penalty>
  <hidden>0</hidden>
  <stackversion><text>2024032401</text></stackversion>
  <questionvariables><text></text></questionvariables>
  <specificfeedback format="html"><text></text></specificfeedback>
  <questionnote><text>Short note for the author dashboard</text></questionnote>
  <questiondescription format="html"><text><![CDATA[This question is based on <source item> in Section <X> of <citation>.]]></text></questiondescription>
  <prtcorrect format="html"><text><![CDATA[[[commonstring key="symbolicprtcorrectfeedback"/]] [[commonstring key="defaultprtcorrectfeedback"/]]]]></text></prtcorrect>
  <prtpartiallycorrect format="html"><text><![CDATA[[[commonstring key="symbolicprtpartiallycorrectfeedback"/]] [[commonstring key="defaultprtpartiallycorrectfeedback"/]]]]></text></prtpartiallycorrect>
  <prtincorrect format="html"><text><![CDATA[[[commonstring key="symbolicprtincorrectfeedback"/]] [[commonstring key="defaultprtincorrectfeedback"/]]]]></text></prtincorrect>
  <decimals>.</decimals>
  <scientificnotation>*10</scientificnotation>
  <multiplicationsign>none</multiplicationsign>
  <sqrtsign>1</sqrtsign>
  <complexno>i</complexno>
  <inversetrig>cos-1</inversetrig>
  <logicsymbol>lang</logicsymbol>
  <matrixparens>[</matrixparens>
  <variantsselectionseed></variantsselectionseed>
  <deployedseed>11111</deployedseed>
</question>
```

The authoring dashboard shows "Question is missing tests or variants" on skinny
STACK, because there are no qtests and no randomisation. Students never see that
warning. It is an accepted cost.

Use `type="description"` only for pure prose and static HTML: headers,
key-point boxes with no figure, exercise dividers. Anything containing
`[[jsxgraph]]` or CASText (`{@...@}`) must be `type="stack"`.

## Template library

Number line with points:

```javascript
// points = [{x: -2, label: '−2'}, {x: 3, label: '3'}]
var line = b.create('line', [[-5,0],[5,0]], {strokeColor:'black'});
points.forEach(function(p) {
  b.create('point', [p.x, 0], {size:4, color:'#003399', fixed:true,
    name:p.label, label:{offset:[0,-18]}});
});
```

Function plot:

```javascript
b.create('functiongraph', [function(x){ return x*x - 2*x - 3; }, -3, 5],
  {strokeColor:'#003399', strokeWidth:2});
```

Shaded region below or between curves: build a polygon from sampled curve points
plus the axis closure. Use `fillColor` and `fillOpacity` on the polygon, with
`borders: { strokeWidth: 0 }` and `vertices: { visible: false }`.
Prototype: `content/reference/prototypes/jsxgraph_shaded_region_prototype.xml`

Interactive slider: `board.create('slider', [startPt, endPt, [min, init, max]])`
with `snapWidth` for discrete steps. Reference the value in `functiongraph`
callbacks via `slider.Value()`, and add a dynamic text label that updates with
it. Prototype: `content/reference/prototypes/jsxgraph_slider_prototype.xml`

## Using CAS variables inside a board

Reference Maxima variables with `{#varname#}` (numeric) or `{@varname@}`
(display) inside the `[[jsxgraph]]` block:

```javascript
b.create('functiongraph', [function(x){ return x*x + {#b#}*x + {#c#}; }],
  {strokeColor:'#003399', strokeWidth:2});
b.create('point', [{#-b/2#}, {#qval#}],
  {size:4, color:'#cc0000', fixed:true, name:'vertex', label:{offset:[10,-15]}});
```

## Known limits

- Figures cannot be screenshotted inside validation. After embedding one, flag
  it for a human to look at. Nothing in the automated pipeline can tell you the
  picture is wrong.
- If a figure is draggable and its state feeds a student answer, the board state
  must be written to a hidden STACK input on board update. That is an advanced
  pattern. Confirm the design with a human before attempting it.
