// objects/static/fence.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const rTop = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.06, 0.05), col2);
                    rTop.position.y = 0.85;
                    const rBot = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.06, 0.05), col2);
                    rBot.position.y = 0.28;
                    g.add(rTop, rBot);
                    for (let x = -0.8; x <= 0.81; x += 0.2) {
                        const picket = new THREE.Mesh(new THREE.BoxGeometry(0.08, 1.05, 0.04), col2);
                        picket.position.set(x, 0.525, 0);
                        g.add(picket);
                    }

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'fence',
    category: 'static',
    staticType: 'fence',
    radius: 0.85,
    build
};
