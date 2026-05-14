// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

import "@openzeppelin/contracts/token/ERC1155/ERC1155.sol";
import "@openzeppelin/contracts/access/AccessControl.sol";
import "@openzeppelin/contracts/security/Pausable.sol";

/**
 * @title SovereignVoucher
 * @dev RWA (Real World Asset) Tokenization Engine for the Marketplace.
 * This contract wraps deterministic voucher keys into ERC-1155 tokens.
 * 
 * Flow:
 * 1. Seller locks a voucher key (e.g. PSN $50) in the Marketplace Vault.
 * 2. The Marketplace Oracle (Minter Role) mints an ERC-1155 token to the Seller's wallet.
 * 3. The token can be traded freely on DEXs or OTC markets.
 * 4. To "redeem" the underlying voucher, the token holder calls `redeem()`, which burns the token
 *    and emits an event. The Marketplace Oracle listens to this event and securely delivers the 
 *    Vault-decrypted voucher key to the redeemer.
 */
contract SovereignVoucher is ERC1155, AccessControl, Pausable {
    bytes32 public constant MINTER_ROLE = keccak256("MINTER_ROLE");
    bytes32 public constant ORACLE_ROLE = keccak256("ORACLE_ROLE");

    // Mapping from Token ID to Nominal USD Value (e.g., 5000 = $50.00)
    mapping(uint256 => uint256) public nominalValues;
    
    // Mapping from Token ID to Asset Metadata URI (e.g., ipfs://...)
    mapping(uint256 => string) public tokenURIs;

    event VoucherMinted(address indexed to, uint256 indexed id, uint256 amount, uint256 nominalValue);
    event VoucherRedeemed(address indexed by, uint256 indexed id, uint256 amount);

    constructor(string memory baseURI) ERC1155(baseURI) {
        _grantRole(DEFAULT_ADMIN_ROLE, msg.sender);
        _grantRole(MINTER_ROLE, msg.sender);
        _grantRole(ORACLE_ROLE, msg.sender);
    }

    /**
     * @dev Mint new tokenized vouchers. Only callable by the Marketplace Oracle.
     */
    function mintVoucher(
        address account, 
        uint256 id, 
        uint256 amount, 
        uint256 nominalValue, 
        string memory _uri, 
        bytes memory data
    ) external onlyRole(MINTER_ROLE) whenNotPaused {
        if (nominalValues[id] == 0) {
            nominalValues[id] = nominalValue;
            tokenURIs[id] = _uri;
        } else {
            require(nominalValues[id] == nominalValue, "Nominal value mismatch");
        }
        
        _mint(account, id, amount, data);
        emit VoucherMinted(account, id, amount, nominalValue);
    }

    /**
     * @dev Burn the tokenized voucher to claim the real-world key.
     * The Marketplace Oracle listens for this event to release the Vault key.
     */
    function redeem(uint256 id, uint256 amount) external whenNotPaused {
        require(balanceOf(msg.sender, id) >= amount, "Insufficient token balance");
        
        _burn(msg.sender, id, amount);
        emit VoucherRedeemed(msg.sender, id, amount);
    }

    function setURI(uint256 id, string memory newuri) external onlyRole(DEFAULT_ADMIN_ROLE) {
        tokenURIs[id] = newuri;
        emit URI(newuri, id);
    }

    function uri(uint256 id) public view override returns (string memory) {
        return tokenURIs[id];
    }

    function pause() external onlyRole(DEFAULT_ADMIN_ROLE) {
        _pause();
    }

    function unpause() external onlyRole(DEFAULT_ADMIN_ROLE) {
        _unpause();
    }

    // Required overrides
    function supportsInterface(bytes4 interfaceId) public view override(ERC1155, AccessControl) returns (bool) {
        return super.supportsInterface(interfaceId);
    }
}
