/**
 * Luhn class.
 * 
 * Wrapper for validating or generating checksum values using the Luhn algorithm
 * for instance Credit Cards, IMEI numbers etc.
 * 
 * Default Usages:
 * <pre>
 * Luhn.validate('4444333322221111') === true;
 * Luhn.calculate('444433332222111') === 1;
 * </pre>
 * 
 * These methods can also be called via an instance:
 * <pre>
 * (new Luhn('444433332222111')).calculate() === 1;
 * </pre>
 * 
 * Includes additional helper functions for processing arrays. Examples:
 * <pre>
 * Array.sum([1, 2, 3]) === 6;
 * [1, 2, 3].sum() === 6;
 * Luhn.read('123') === [3, 2, 1];
 * Luhn.transform([1, 2, 3]) === [2, 4, 6];
 * </pre>
 * 
 * @type Luhn
 */
var Luhn = (function(){
    // Local Constants
    var t = !0,     // true
        f = !t,     // false
        u = void 0; // undefined
    
    /**
     * Array function 'sum'.
     * 
     * Adds numerical values together and returns the total.
     * 
     * @param {type} arr
     * @returns {unresolved}
     */
    Array.sum = Array.sum || function(arr) {
        // Reduce array by summing elements.
        return arr.reduce(function(a, b){
            // Ensure only numerical elements are summed. Non-numerical elements will be
            // treated as adding zero. Strings should only count if they start with digits.
            return (parseFloat(a) || 0) + (parseFloat(b) || 0);
        });
    };

    /**
     * Add a prototype function for summing an array.
     * 
     * @uses Array.sum(this);
     * @default arr.sum();
     * 
     * @returns {Number}
     */
    Object.defineProperty(Array.prototype, 'sum', {
        // Prevent this function from being enumerable/loopable.
        enumerable: false,
        // Use an existing implementation if found.
        value: Array.prototype.sum || function() {
            return Array.sum(this);
        }
    });
    
    /**
     * Card constructor - save unformatted pan and checksum.
     * 
     * @param {String|Number} number
     * @param {String|Number} checksum
     */
    Luhn = function(number, checksum) {
        this.pan = number;
        this.csm = checksum;
    };
    
    // Card constant - Transform array (seems to be the most efficient method).
    Luhn.Transform = [0, 2, 4, 6, 8, 1, 3, 5, 7, 9];
    
    /**
     * Double all single digit integers and reduce by 9 when 10 or over.
     * 
     * This transforms digits to the following:
     * 0 => 0, 1 => 2, 2 => 4, 3 => 6, 4 => 8
     * 5 => 1, 6 => 3, 7 => 5, 8 => 7, 9 => 9
     * 
     * Function named for readability, as it starts by transforming the FIRST element.
     * The fact that indexes start at 0 is trivial since they can start at any number.
     * 
     * @param {Array} number
     * @returns {Array}
     */
    Luhn.transform = function(pan) {
        for (var i = 0; i < pan.length; i += 2) {
            pan[i] = Luhn.Transform[pan[i]];
        }
        return pan;
    };
    
    /**
     * Remove all non-digits from a string, convert to an array, reverse the order,
     * and ensure all elements are converted back to numbers.
     * 
     * @param {Number|String} pan
     * @returns {String}
     */
    Luhn.read = function(number) {
         return ('' + number).replace(/\D+/ig, '').split('').reverse().map(parseFloat);
    };
    
    /**
     * Verifies that the provided checksum is valid for the matching pan.
     * 
     * The last digit of the pan will be used as the checksum value if an
     * invalid checksum is supplied.
     *
     * @param {Number|String} number
     * @param {Number|String} checksum
     * @returns {Boolean}
     */
    Luhn.validate = function(number, checksum) {
        // Parse both pan and checksum.
        var pan = Luhn.read(number);
        var csm = Luhn.read(checksum);
        
        // No checksum provided - use last digit.
        if (csm.length === 0) {
            csm = pan.splice(0, 1);
        }
        // Invalid number of digits found for either checksum or pan. Instant fail.
        if (pan.length < 11 || csm.length !== 1) {
            return false;
        }
        
        // Transform and sum the digits to ensure they add to a number divisble by 10.
        var transform = Luhn.transform(pan);
        return (transform.sum() + csm.sum()) % 10 === 0;
    };
    
    /**
     * Similar to Card.validate - Allows just the checksum to be provided, and
     * the stored pan will be used to validate it. The last digit of the stored
     * pan will be used if no checksum is provided or saved.
     */
    Object.defineProperty(Luhn.prototype, 'validate', {
        enumerable: f,
        /**
         * @param {number|String} number Either the pan or checksum to validate.
         * @param {number|String} checksum Number needs to be valid when the
         * checksum is the second argument.
         * @returns {Boolean}
         */
        value: function(number, checksum) {
            var pan = Luhn.read(number);
            var csm = Luhn.read(checksum);
            
            // Checksum was either the first argument or not provided.
            if (pan.length < 2) {
                csm = pan; // Assume first argument then load pan from saved data.
                pan = Luhn.read(this.pan);
            }
            // Checksum not provided properly. Use saved checksum.
            if (csm.length === 0) {
                csm = Luhn.read(this.csm);
            }
            // No checksum provided or saved - use last digit.
            if (csm.length === 0) {
                csm = pan.splice(0, 1);
            }
            // Invalid number of digits found for either checksum or pan. Instant fail.
            if (pan.length < 11 || csm.length !== 1) {
                return false;
            }
            
            // Transform and sum the digits to ensure they add to a number divisble by 10.
            var transform = Luhn.transform(pan);
            return (transform.sum() + csm.sum()) % 10 === 0;
        }
    });
    
    /**
     * Calculate the checksum for a provided number.
     * 
     * @param {Number|String} number
     * @returns {Number}
     */
    Luhn.calculate = function(number) {
        // Calculate pan and ensure it's the appropriate length.
        var pan = Luhn.read(number);
        if (pan.length < 11) {
            return -1;
        }
        
        // Transform and sum digits to find the remainder from modulo 10.
        var transform = Luhn.transform(pan);
        return (10 - (transform.sum() % 10)) % 10; // Convert 10 to zero.
    };
    
    /**
     * Similar to Card.calculate - will use the saved pan if not provided.
     */
    Object.defineProperty(Luhn.prototype, 'calculate', {
        enumerable: f,
        /**
         * Calculate the checksum for a provided or saved number.
         * Assumes that checksum is not included in either pan or number.
         * 
         * @param {Number|String} number
         * @returns {Number}
         */
        value: function(number) {
            // Calculate pan
            var pan = Luhn.read(number);
            // No number supplied - fall back to saved pan.
            if (pan.length === 0) {
                pan = Luhn.read(this.pan);
            }
            // Ensure pan is the appropriate length.
            if (pan.length < 11) {
                return -1;
            }
            
            // Transform and sum digits to find the remainder from modulo 10.
            var transform = Luhn.transform(pan);
            return (10 - (transform.sum() % 10)) % 10; // Convert 10 to zero.
        }
    });
    
    return Luhn;
})();

// Module exports for when bundled using gulp/browserify etc.
if (typeof Module === 'object') {
    Module.exports = Luhn;
}
