// objects/static/sofa.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const base = new THREE.Mesh(new THREE.BoxGeometry(2.1, 0.42, 0.88), col1);
                    base.position.y = 0.21;
                    const back = new THREE.Mesh(new THREE.BoxGeometry(2.1, 0.62, 0.22), col1);
                    back.position.set(0, 0.62, -0.33);
                    [-0.95, 0.95].forEach(ax => {
                        const arm = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.56, 0.88), col2);
                        arm.position.set(ax, 0.28, 0);
                        g.add(arm);
                    });
                    g.add(base, back);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'sofa',
    category: 'static',
    staticType: 'sofa',
    radius: 0.85,
    build
};
