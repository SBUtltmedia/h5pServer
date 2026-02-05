/**
 * @fileoverview
 * This library provides a utility for a Gemini Canvas Game, running within an iframe,
 * to communicate with its parent LTI 1.3 Tool Provider.
 *
 * @instructions_for_ai:
 * This code is designed to be used by a Gemini Canvas game. When a new game is created,
 * the AI should provide this file to the user and instruct them to include it as a
 * script tag in their game's HTML file.
 *
 * To initiate communication with the parent frame, the game's code should call
 * the `CanvasGame.notifyParent` function. The function requires an `action` string
 * and an optional `payload` object. The `action` should be a clear, descriptive
 * string (e.g., "game_complete", "score_update", "level_unlocked"). The `payload`
 * should be a JSON object containing any relevant data to send to the parent.
 *
 * Example usage from within a game's JavaScript:
 *
 * // When the game is finished, send a message to the parent frame
 * const finalScore = 1250;
 * CanvasGame.notifyParent('game_complete', { score: finalScore, time_in_seconds: 120 });
 *
 * // When a player needs more lives, notify the parent frame
 * CanvasGame.notifyParent('need_lives');
 *
 * // Save game state
 * CanvasGame.save({ level: 5, inventory: ['sword', 'shield'] });
 *
 * // Load game state
 * CanvasGame.requestLoad();
 * CanvasGame.onLoad((data) => {
 *   console.log('Loaded state:', data);
 * });
 *
 * The parent frame will listen for these messages and handle them accordingly.
 * The AI should generate a complete game that uses this library to communicate its
 * state, such as game completion, final score, and saving/loading.
 *
 * Note on security: `postMessage` is used to send the data. A target origin of
 * '*' is used for simplicity. For production environments, the AI should instruct
 * the developer to replace '*' with the specific origin of the LTI tool provider
 * to prevent messages from being sent to unintended destinations.
 */

// A simple namespace object to hold the library's functionality.
const CanvasGame = {};

/**
 * Sends a message from the iframe to its parent window.
 * This is the primary method for a Gemini Canvas game to communicate with its LTI tool provider.
 *
 * @param {string} action - A string representing the action or event that occurred in the game (e.g., "game_complete", "score_update").
 * @param {object} [payload={}] - An optional JSON object containing data relevant to the action.
 */
CanvasGame.notifyParent = (action, payload = {}) => {
  if (window.parent) {
    try {
      const message = {
        source: 'gemini-canvas-game',
        action: action,
        data: payload
      };
      // Send the message to the parent window. Using a secure origin is recommended
      // in a production environment.
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
 * Sends a request to the parent window to save the current game state.
 * @param {object} data - The game state object to save.
 */
CanvasGame.save = (data) => {
    CanvasGame.notifyParent('save_state', data);
};

/**
 * Sends a request to the parent window to load the saved game state.
 */
CanvasGame.requestLoad = () => {
    CanvasGame.notifyParent('load_state');
};

/**
 * Registers a callback function to handle the loaded game state received from the parent window.
 * @param {function} callback - The function to call when the state is loaded. It receives the state data as an argument.
 */
CanvasGame.onLoad = (callback) => {
    window.addEventListener('message', (event) => {
        // Validate the message source and action
        if (event.data && event.data.source === 'gemini-canvas-parent' && event.data.action === 'load_state_response') {
            callback(event.data.data);
        }
    });
};