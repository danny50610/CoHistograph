/**
 * Backdrop dismiss should only happen when both press and release land on the backdrop.
 * This avoids closing the modal when a text selection starts inside an input and ends outside.
 */
export function shouldCloseModalOnBackdropRelease(mouseDownOnBackdrop, mouseUpOnBackdrop) {
    return mouseDownOnBackdrop && mouseUpOnBackdrop;
}
