/* jshint esversion: 6 */

/**
 * Manages the vertical draggable bar between left and right columns for resizing.
 */
window.DragBar = class {
    /**
     * Initialize the drag bar functionality.
     */
    constructor() {
        this.dragBar = document.querySelector('.drag-bar');
        this.leftColumn = document.querySelector('.left-column');
        this.rightColumn = document.querySelector('.right-column');

        this.isDragging = false;
        this.currentX = 0;
        this.leftColumnWidth = 0;
        this.rightColumnWidth = 0;

        // Bind event handlers to maintain 'this' context
        this.handleMouseMove = this.handleMouseMove.bind(this);
        this.handleMouseDown = this.handleMouseDown.bind(this);
        this.handleMouseUp = this.handleMouseUp.bind(this);

        this.initializeEventListeners();
    }

    /**
     * Set up event listeners for the drag bar.
     */
    initializeEventListeners() {
        this.dragBar.addEventListener('mousedown', this.handleMouseDown);
        document.addEventListener('mousemove', this.handleMouseMove);
        document.addEventListener('mouseup', this.handleMouseUp);
    }

    /**
     * Handle mouse move events for dragging.
     * @param {MouseEvent} e - The mouse event.
     */
    handleMouseMove(e) {
        if (this.isDragging) {
            e.preventDefault();
            const deltaX = e.clientX - this.currentX;
            const newLeftColumnWidth = Math.max(50, this.leftColumnWidth + deltaX);
            const newRightColumnWidth = Math.max(50, this.rightColumnWidth - deltaX);

            this.leftColumn.style.width = `${newLeftColumnWidth}px`;
            this.rightColumn.style.width = `${newRightColumnWidth}px`;
        }
    }

    /**
     * Handle mouse down event on the drag bar.
     * @param {MouseEvent} e - The mouse event.
     */
    handleMouseDown(e) {
        // Reselect right column as it may have been recreated
        this.rightColumn = document.querySelector('.right-column');
        this.isDragging = true;
        this.currentX = e.clientX;
        this.leftColumnWidth = this.leftColumn.offsetWidth;
        this.rightColumnWidth = this.rightColumn.offsetWidth;
    }

    /**
     * Handle mouse up event to stop dragging.
     */
    handleMouseUp() {
        this.isDragging = false;
    }

    /**
     * Static method to initialize the drag bar.
     * This can be used as an event handler directly.
     */
    static init() {
        // noinspection ObjectAllocationIgnored
        new DragBar();
    }
};

// Initialize the drag bar when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', DragBar.init);
