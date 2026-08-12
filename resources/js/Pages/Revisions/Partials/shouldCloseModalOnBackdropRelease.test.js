import { describe, it } from 'node:test';
import assert from 'node:assert/strict';
import { shouldCloseModalOnBackdropRelease } from './shouldCloseModalOnBackdropRelease.js';

describe('shouldCloseModalOnBackdropRelease', () => {
    it('closes when both press and release are on the backdrop', () => {
        assert.equal(shouldCloseModalOnBackdropRelease(true, true), true);
    });

    it('does not close when selection starts inside the modal and ends on the backdrop', () => {
        assert.equal(shouldCloseModalOnBackdropRelease(false, true), false);
    });

    it('does not close when press starts on the backdrop and release is inside the modal', () => {
        assert.equal(shouldCloseModalOnBackdropRelease(true, false), false);
    });

    it('does not close when neither event is on the backdrop', () => {
        assert.equal(shouldCloseModalOnBackdropRelease(false, false), false);
    });
});
