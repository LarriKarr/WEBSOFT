// objects/static/trashbin.js — 3D модель ресурса
export function build(d = {}) {
    const g = new THREE.Group();
    const s = parseFloat(d.scale || 1.0);
    const col1 = new THREE.MeshStandardMaterial({ color: d.color || '#15803d', roughness: 0.7 });
    const col2 = new THREE.MeshStandardMaterial({ color: d.accent || '#78350f', roughness: 0.8 });
    const metalMat = new THREE.MeshStandardMaterial({ color: 0x1f2937, metalness: 0.7, roughness: 0.3 });

    const bin = new THREE.Mesh(new THREE.CylinderGeometry(0.24, 0.2, 0.62, 14), metalMat);
                    bin.position.y = 0.31;
                    const rim = new THREE.Mesh(new THREE.TorusGeometry(0.24, 0.02, 8, 18), col1);
                    rim.rotation.x = Math.PI / 2;
                    rim.position.y = 0.62;
                    g.add(bin, rim);

    g.scale.set(s, s, s);
    g.traverse(m => { if (m.isMesh) { m.castShadow = true; m.receiveShadow = true; } });
    return g;
}

export default {
    id: 'trashbin',
    category: 'static',
    staticType: 'trashbin',
    radius: 0.45,
    build
};
