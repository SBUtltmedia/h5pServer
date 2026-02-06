# SBUFramer: Gemini Game Integration Guide

This guide explains how to modify an existing HTML5 game (or create a new one) to integrate with the SBUFramer LMS Connector. The connector allows games to be played within an iframe while communicating scores and game state (save/load) to the parent system (LTI/Shibboleth).

## Overview

The game runs inside an iframe. To ensure security and proper data handling, the game **must not** directly access backend scripts. Instead, it communicates with the parent frame using `window.postMessage`.

## Integration Steps

### 1. Add the Integration Code

Include the following JavaScript code directly in your game's HTML file (e.g., inside a `<script>` tag at the beginning of your logic). You do **not** need an external library file.

```javascript
const CanvasGame = {};

/**
 * Sends a message from the iframe to its parent window.
 */
CanvasGame.notifyParent = (action, payload = {}) => {
  if (window.parent) {
    try {
      const message = {
        source: 'gemini-canvas-game',
        action: action,
        data: payload
      };
      window.parent.postMessage(message, '*');
      console.log('Message sent to parent:', message);
    } catch (error) {
      console.error('Error sending message to parent:', error);
    }
  } else {
    console.warn('The game is not running in an iframe, cannot communicate with a parent.');
  }
};

/**
 * Save game state object.
 */
CanvasGame.save = (data) => {
    CanvasGame.notifyParent('save_state', data);
};

/**
 * Request saved game state.
 */
CanvasGame.requestLoad = () => {
    CanvasGame.notifyParent('load_state');
};

/**
 * Register callback for loaded state.
 */
CanvasGame.onLoad = (callback) => {
    window.addEventListener('message', (event) => {
        if (event.data && event.data.source === 'gemini-canvas-parent' && event.data.action === 'load_state_response') {
            callback(event.data.data);
        }
    });
};
```

### 2. Sending Scores

To report a score or game completion, use `CanvasGame.notifyParent`.

**Progressive Score Update:**
```javascript
// Score is typically a value between 0 and 1 (percentage) or a raw number.
CanvasGame.notifyParent('score_update', { score: 0.85 });
```

**Game Completion:**
```javascript
CanvasGame.notifyParent('game_complete', { score: 1.0 });
```

### 3. Saving and Loading State

The system provides persistent JSON storage for each user per game.

**Saving State:**
Call `CanvasGame.save(data)` with a JSON-serializable object.

```javascript
const gameState = {
    level: 3,
    inventory: ['sword', 'key'],
    health: 75
};
CanvasGame.save(gameState);
```

**Loading State (Recommended Pattern):**
Request the state during initialization and use a fallback if no state is found.

```javascript
document.addEventListener('DOMContentLoaded', () => {
    let stateLoaded = false;

    // 1. Define what to do when state is loaded
    CanvasGame.onLoad((savedState) => {
        stateLoaded = true;
        if (savedState) {
            console.log("Restoring game state...", savedState);
            initializeGame(savedState);
        } else {
            console.log("No saved state found. Starting fresh.");
            initializeGame(null);
        }
    });

    // 2. Request the state
    CanvasGame.requestLoad();

    // 3. Fallback if no response (timeout)
    setTimeout(() => {
        if (!stateLoaded) {
            console.warn("State load timeout. Starting fresh.");
            initializeGame(null);
        }
    }, 500);
});
```

## API Reference

*   `CanvasGame.notifyParent(action, payload)`: Sends a raw message to the parent.
    *   `action` (string): Event type (e.g., 'score_update').
    *   `payload` (object): Data to send.
*   `CanvasGame.save(data)`: Helper to save game state object.
*   `CanvasGame.requestLoad()`: Helper to request saved state.
*   `CanvasGame.onLoad(callback)`: Helper to listen for the saved state response.

## Architecture

*   **Game (Child Frame)**: `<iframe src="...">`
    *   Sends `postMessage` commands.
*   **Connector (Parent Frame)**: `index.php`
    *   Listens for `postMessage`.
    *   Handles AJAX calls to `games/.../saves/gameState.php` on behalf of the game.
    *   Persists data to `<user_email>.json`.