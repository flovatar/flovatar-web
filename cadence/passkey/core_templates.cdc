// Read-only: series-1 core templates (for the mini builder of the PoC).
import FlovatarComponentTemplate from 0xFlovatar

access(all) fun main(): [{String: AnyStruct}] {
    var out: [{String: AnyStruct}] = []
    for t in FlovatarComponentTemplate.getComponentTemplates() {
        if ["body", "hair", "eyes", "nose", "mouth", "clothing"].contains(t.category) {
            out.append({"id": t.id, "name": t.name, "category": t.category, "rarity": t.rarity, "series": t.series})
        }
    }
    return out
}
