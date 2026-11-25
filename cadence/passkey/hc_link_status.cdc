// Read-only: HybridCustody link state of a passkey account (child) and, optionally, one parent wallet.
import HybridCustody from 0xHybridCustody

access(all) fun main(child: Address, parent: Address?): {String: AnyStruct} {
    var parents: {Address: Bool} = {}
    if let owned = getAccount(child).capabilities.borrow<&{HybridCustody.OwnedAccountPublic}>(HybridCustody.OwnedAccountPublicPath) {
        parents = owned.getParentStatuses()
    }
    var managerHasChild = false
    if parent != nil {
        if let m = getAccount(parent!).capabilities.borrow<&{HybridCustody.ManagerPublic}>(HybridCustody.ManagerPublicPath) {
            managerHasChild = m.getChildAddresses().contains(child)
        }
    }
    return {"parents": parents, "managerHasChild": managerHasChild}
}
