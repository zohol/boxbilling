![Build Status](https://bamboo.coinify.com/plugins/servlet/wittified/build-status/INT-BOXBILLING)

About
=====
+ Coinify for BoxBilling.
+ Version 1.1

System Requirements
===================
+ Curl PHP Extension
+ JSON Encode

Configuration Instructions
==========================
    1. Upload files to your BoxBilling installation.
    2. Go to your BoxBilling configuration. Payment Gateways -> New payment gateway -> "Coinify" click [Install]
    3. In Coinify Instant Payment Notification (https://coinify.com/merchant/ipn) Enter the link to your callback of Coinify BoxBilling Payment Module, located under settings of module.
    4. Enter a strong Secret in Coinify Secret.
    5. In module settings "API" <- set your Coinify invoice API key, which can be generate under API Keys, Invoice.
    6. In module settings "Secret" <- Enter your Coinify Secret.

Changelog:
===================
	Version 1.1 (February 10, 2016)
	Fix bug with wrong variable name
	
	
### Tested with:

+ BoxBilling v3.6.11
+ BoxBilling v4.20

#### Disclaimer:

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.



  



