/**
 * Password Class.
 * 
 * Generate a password with specific requirements, for instance 'must contain a
 * letter, number, capital and a symbol, and/or must be N characters long.'
 * 
 * Default Usage:
 * var password = new Password + '';
 * 
 * @type Password
 */
var Password = (function() {
    // Local constants - used for minifying true to a single character etc.
    var t = true,
        f = !t,
        u = void 0;
    
    // Password class / constructor.
    function Password(options) {
        this.init(options);
    };
    
    // Set Default properties; enumerable, writable and configurable by default.
    Password.prototype.length  = 9;
    Password.prototype.caps    = t;
    Password.prototype.letters = t;
    Password.prototype.numbers = t;
    Password.prototype.symbols = t;
    Password.prototype.ignore  = '';
    
    // Class constants
    Password.strings = {
        caps    : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        letters : 'abcdefghijklmnopqrstuvwxyz',
        numbers : '0123456789',
        symbols : '~!@#$%^&*()-_=+[{]}\\|;:,<.>/?'
    };
    Password.escapeRegExp = /[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g;
    
    /**
     * Helper Function: generate a regex match for a given string of characters.
     * 
     * @param {string} chars unformatted list of characters.
     * @param {string} list of flags to include in RegExp.
     * @returns {RegExp} RegExp where characters are escaped and grouped.
     */
    Password.regexp = function(chars, flags) {
        return new RegExp('[' + chars.replace(Password.escapeRegExp, '\\$&') + ']', flags);
    };
    
    /**
     * Helper fucntion: Obtain a character at random from a supplied string.
     * 
     * @param {string} chars a list of characters to select from as a string.
     * @returns {string} A single character from the supplied character list.
     */
    Password.getChar = function(chars) {
        return chars.charAt(Math.floor(Math.random() * chars.length));
    };
    
    // Init method. Update default properties.
    Object.defineProperty(Password.prototype, 'init', {
        enumerable: f,
        /**
         * Update options - make sure keys match where possible.
         * 
         * @param {object} options Associative list of options. Should match
         * each of the default properties found in the constructor.
         */
        value: function(options) {
            if (typeof options === 'object') {
                // Obtain prototype descriptors to ensure supplied options are writable, enumable and configurable.
                var descriptors = Object.getOwnPropertyDescriptors(Password.prototype);
                for (var i in options) {
                    if (options.hasOwnProperty(i) && (this.hasOwnProperty(i) || (descriptors.hasOwnProperty(i)
                    && (descriptors[i].writable && descriptors[i].enumerable && descriptors[i].configurable)))) {
                        this[i] = options[i];
                    }
                }
            }
        }
    });
    
    // Generate Method. Returns a password string.
    Object.defineProperty(Password.prototype, 'generate', {
        enumerable: f,
        /**
         * Generate password based on the supplied/saved/default properties.
         * 
         * @param {object} options Associative list of options. Should match
         * each of the default properties found in the constructor.
         * @returns {string} Password string.
         */
        value: function(options) {
            this.init(options);
            var password = '',
                validate = this.length,
                chars    = this.genChars(password);
            for (var i in Password.strings) {
                if (Password.strings.hasOwnProperty(i) && this[i]) {
                    validate--;
                }
            }
            for (var i = 0; i < this.length; i++) {
                // Refresh character list near the end of the password to ensure
                // that each group of characters is used at least once.
                if (i > validate) {
                    chars = this.genChars(password);
                }
                // Append character at random from character list.
                password += Password.getChar(chars);
            }
            return password;
        }
    });
    
    // genChars Method. Returns list of characters as a string.
    Object.defineProperty(Password.prototype, 'genChars', {
        enumerable: f,
        /**
         * Worker function: generate list of available chars based on current
         * password string and saved properties.
         * 
         * @param {string} password Current password.
         * @param {object} options Associative list of options. Should match
         * each of the default properties found in the constructor.
         * @return {string} List of possible characters to generate the rest of
         * the password.
         */
        value: function(password, options) {
            this.init(options);
            var chars   = '';
            // Include list of characters from each group if they aren't yet present.
            for (var i in Password.strings) {
                if (Password.strings.hasOwnProperty(i) && this[i]) {
                    if (!Password.regexp(Password.strings[i]).test(password)) {
                        chars += Password.strings[i];
                    }
                }
            }
            // Current list is empty;
            if (!chars) {
                // Include list of all characters from all selected groups.
                for (var i in Password.strings) {
                    if (Password.strings.hasOwnProperty(i) && this[i]) {
                        chars += Password.strings[i];
                    }
                }
            }
            // Ignore specific characters as requested.
            if (this.ignore) {
                chars = chars.replace(Password.regexp(this.ignore, 'g'), '');
            }
            return chars;
        }
    });
    
    // Replace default toString function to return a new password each time.
    Object.defineProperty(Password.prototype, 'toString', {
        enumerable: f,
        value: function(options) {
            return this.generate(options);
        }
    });
    
    return Password;
})();

// Module exports for when bundled using gulp/browserify etc.
if (typeof Module === 'object') {
    Module.exports = Password;
}
