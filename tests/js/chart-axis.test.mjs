import {test} from 'node:test';
import assert from 'node:assert/strict';
import {historyAxis} from '../../resources/js/chart-axis.js';
test('one-year view ends at its last observation and labels calendar months with their years',()=>{
 const axis=historyAxis('1y','2025-10-14T00:00:00Z','2026-10-13T15:34:43Z');
 assert.equal(axis.min,Date.parse('2025-10-14T00:00:00Z'));
 assert.equal(axis.max,Date.parse('2026-10-13T15:34:43Z'));
 const scale={};axis.afterBuildTicks(scale);
 assert.equal(scale.ticks[0].value,axis.min);
 assert.equal(scale.ticks.at(-1).value,axis.max);
 assert.ok(scale.ticks.every(t=>t.value>=axis.min&&t.value<=axis.max));
 assert.equal(axis.label(axis.min),'Oct 2025');
 assert.equal(axis.label(axis.max),'Oct 2026');
 assert.equal(scale.ticks.length,13);
 assert.ok(scale.ticks.slice(1,-1).every(t=>new Date(t.value).getUTCDate()===1));
});
test('short ranges keep daily or hourly labels and include the year when crossing New Year',()=>{
 const daily=historyAxis('7d','2025-12-29T00:00:00Z','2026-01-04T12:00:00Z');
 assert.equal(daily.afterBuildTicks,undefined);
 assert.equal(daily.label(daily.min),'29 Dec 2025');
 assert.equal(daily.label(daily.max),'4 Jan 2026');
 const hourly=historyAxis('24h','2026-10-13T10:00:00Z','2026-10-13T15:00:00Z');
 assert.equal(hourly.label(hourly.min),'10:00');
});
